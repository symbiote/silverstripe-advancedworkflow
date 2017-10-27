<?php

namespace Symbiote\AdvancedWorkflow\Actions;

use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\LabelField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\DataObject;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowAction;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowInstance;
use Symbiote\AdvancedWorkflow\Extensions\WorkflowEmbargoExpiryExtension;
use Symbiote\AdvancedWorkflow\Jobs\WorkflowPublishTargetJob;
use Symbiote\QueuedJob\Services\AbstractQueuedJob;
use Symbiote\QueuedJob\Services\QueuedJobService;

/**
 * Unpublishes an item
 *
 * @author     marcus@symbiote.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage actions
 */
class UnpublishItemWorkflowAction extends WorkflowAction
{
    private static $db = array(
        'UnpublishDelay' => 'Int'
    );

    private static $icon = 'symbiote/silverstripe-advancedworkflow:images/unpublish.png';

    private static $table_name = 'UnpublishItemWorkflowAction';

    public function execute(WorkflowInstance $workflow)
    {
        if (!$target = $workflow->getTarget()) {
            return true;
        }

        if (class_exists(AbstractQueuedJob::class) && $this->UnpublishDelay) {
            $job   = new WorkflowPublishTargetJob($target, "unpublish");
            $days  = $this->UnpublishDelay;
            $after = date('Y-m-d H:i:s', strtotime("+$days days"));
            singleton(QueuedJobService::class)->queueJob($job, $after);
        } elseif ($target->hasExtension(WorkflowEmbargoExpiryExtension::class)) {
            // setting future date stuff if needbe

            // set these values regardless
            $target->DesiredUnPublishDate = '';
            $target->DesiredPublishDate = '';
            $target->write();

            if ($target->hasMethod('doUnpublish')) {
                $target->doUnpublish();
            }
        } else {
            if ($target->hasMethod('doUnpublish')) {
                $target->doUnpublish();
            }
        }

        return true;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        if (class_exists(AbstractQueuedJob::class)) {
            $before = _t('UnpublishItemWorkflowAction.DELAYUNPUBDAYSBEFORE', 'Delay unpublishing by ');
            $after  = _t('UnpublishItemWorkflowAction.DELAYUNPUBDAYSAFTER', ' days');

            $fields->addFieldToTab('Root.Main', new FieldGroup(
                _t('UnpublishItemWorkflowAction.UNPUBLICATIONDELAY', 'Delay Un-publishing'),
                new LabelField('UnpublishDelayBefore', $before),
                new NumericField('UnpublishDelay', ''),
                new LabelField('UnpublishDelayAfter', $after)
            ));
        }

        return $fields;
    }

    /**
     * @param  DataObject $target
     * @return bool
     */
    public function canPublishTarget(DataObject $target)
    {
        return false;
    }
}
