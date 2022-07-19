<?php

namespace Symbiote\AdvancedWorkflow\Actions;

use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\LabelField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowAction;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowInstance;
use Terraformers\EmbargoExpiry\Extension\EmbargoExpiryExtension;

/**
 * Unpublishes an item or approves it for publishing/un-publishing through queued jobs.
 *
 * @author     marcus@symbiote.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage actions
 * @property int $UnpublishDelay
 */
class UnpublishItemWorkflowAction extends WorkflowAction
{
    private static $db = [
        'UnpublishDelay' => 'Int',
    ];

    private static $icon = 'symbiote/silverstripe-advancedworkflow:images/unpublish.png';

    private static $table_name = 'UnpublishItemWorkflowAction';

    public function execute(WorkflowInstance $workflow)
    {
        $target = $workflow->getTarget();

        if (!$target) {
            return true;
        }

        if ($this->targetRequestsDelayedAction($target)) {
            $this->queueEmbargoExpiryJobs($target);

            $target->write();
        } elseif ($target->hasExtension(Versioned::class)) {
            /** @var DataObject|Versioned $target */
            $target->doUnpublish();
        }

        return true;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // We don't have access to our Target in this context, so we'll just perform a sanity check to see if the
        // Embargo & Expiry module is (generally) present
        if (class_exists(EmbargoExpiryExtension::class)) {
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

    /**
     * @param DataObject|EmbargoExpiryExtension $target
     */
    public function targetRequestsDelayedAction(DataObject $target)
    {
        if (!$this->targetHasEmbargoExpiryModules($target)) {
            return false;
        }

        if ($target->getDesiredPublishDateAsTimestamp() > 0
            || $target->getDesiredUnPublishDateAsTimestamp() > 0
        ) {
            return true;
        }

        if ($this->UnpublishDelay) {
            return true;
        }

        return false;
    }

    /**
     * @param DataObject|EmbargoExpiryExtension $target
     */
    public function queueEmbargoExpiryJobs(DataObject $target): void
    {
        // Can't queue any jobs if we're missing the Embargo & Expiry module
        if (!$this->targetHasEmbargoExpiryModules($target)) {
            return;
        }

        // Queue PublishJob if it's required
        $target->ensurePublishJob();

        // Queue UnPublishJob if it's required. UnpublishDelay always take priority over DesiredUnPublishDate, so exit
        // early if we queue a job here
        if ($this->UnpublishDelay) {
            $target->createOrUpdateUnPublishJob(strtotime("+{$this->UnpublishDelay} days"));

            return;
        }

        // Queue UnPublishJob if it's required
        $target->ensureUnPublishJob();
    }
}
