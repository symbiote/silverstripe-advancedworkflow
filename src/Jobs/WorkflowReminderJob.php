<?php

namespace Symbiote\AdvancedWorkflow\Jobs;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Email\Email;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowInstance;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

if (!class_exists(AbstractQueuedJob::class)) {
    return;
}

/**
 * @author <marcus@symbiote.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class WorkflowReminderJob extends AbstractQueuedJob
{
    const DEFAULT_REPEAT = 600;

    /**
     *
     * @var QueuedJobService
     */
    public $queuedJobService;

    public function __construct($repeatInterval = 0)
    {
        if (!$this->repeatInterval) {
            $this->repeatInterval = $repeatInterval ? $repeatInterval : self::DEFAULT_REPEAT;
            $this->totalSteps = 2;
            $this->currentStep = 1;
        }
    }

    public function getTitle()
    {
        return _t('AdvancedWorkflow.WORKFLOW_REMINDER_JOB', 'Workflow Reminder Job');
    }

    /**
     * We only want one instance of this job ever
     *
     * @return string
     */
    public function getSignature()
    {
        return md5($this->getTitle());
    }

    public function process()
    {
        $sent   = 0;
        $filter = [
            'WorkflowStatus'                    => ['Active', 'Paused'],
            'Definition.RemindDays:GreaterThan' => 0
        ];

        $active = WorkflowInstance::get()->filter($filter);

        foreach ($active as $instance) {
            $edited = strtotime($instance->LastEdited);
            $days   = $instance->Definition()->RemindDays;

            if ($edited + ($days * 3600 * 24) > time()) {
                continue;
            }

            $email   = Email::create();
            $bcc     = '';
            $members = $instance->getAssignedMembers();
            $target  = $instance->getTarget();

            if (!$members || !$members->exists()) {
                continue;
            }

            $email->setSubject("Workflow Reminder: $instance->Title");
            $email->setBcc(implode(', ', $members->column('Email')));
            $email->setHTMLTemplate('WorkflowReminderEmail');
            $email->setData(array(
                'Instance' => $instance,
                'Link'     => $target instanceof SiteTree ? "admin/show/$target->ID" : ''
            ));
            
            try {
                $email->send();
            } catch (Exception $ex) {
                Injector::inst()->get(LoggerInterface::class)->warning($ex->getMessage());
            }
            
            $sent++;

            // add a comment to the workflow if possible
            $action = $instance->CurrentAction();

            $currentComment = $action->Comment;
            $action->Comment = sprintf(_t(
                'AdvancedWorkflow.JOB_REMINDER_COMMENT',
                '%s: Reminder email sent\n\n'
            ), date('Y-m-d H:i:s')) . $currentComment;
            try {
                $action->write();
            } catch (Exception $ex) {
                Injector::inst()->get(LoggerInterface::class)->warning($ex->getMessage());
            }

            $instance->LastEdited = time();
            try {
                $instance->write();
            } catch (Exception $ex) {
                Injector::inst()->get(LoggerInterface::class)->warning($ex->getMessage());
            }
        }

        $this->currentStep = 2;
        $this->isComplete = true;

        $nextDate = date('Y-m-d H:i:s', time() + $this->repeatInterval);
        $this->queuedJobService->queueJob(new WorkflowReminderJob($this->repeatInterval), $nextDate);
    }
}
