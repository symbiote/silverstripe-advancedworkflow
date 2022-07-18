<?php

namespace Symbiote\AdvancedWorkflow\Actions;

use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowAction;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowInstance;
use Terraformers\EmbargoExpiry\Extension\EmbargoExpiryExtension;

/**
 * Publishes an item
 *
 * @author     marcus@symbiote.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage actions
 * @property int $PublishDelay
 */
class PublishItemWorkflowAction extends WorkflowAction
{
    private static $db = [
        'PublishDelay' => 'Int',
    ];

    private static $icon = 'symbiote/silverstripe-advancedworkflow:images/publish.png';

    private static $table_name = 'PublishItemWorkflowAction';

    public function execute(WorkflowInstance $workflow)
    {
        $target = $workflow->getTarget();

        if (!$target) {
            return true;
        }

        if ($this->targetRequestsDelayedAction($target)) {
            $this->queueEmbargoExpiryJobs($target);

            $target->write();
        } else if ($target->hasExtension(Versioned::class)) {
            /** @var DataObject|Versioned $target */
            $target->publishRecursive();
        }

        return true;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // We don't have access to our Target in this context, so we'll just perform a sanity check to see if the
        // Embargo & Expiry module is (generally) present
        if (class_exists(EmbargoExpiryExtension::class)) {
            $fields->addFieldToTab(
                'Root.Main',
                NumericField::create(
                    'PublishDelay',
                    _t('PublishItemWorkflowAction.PUBLICATIONDELAY', 'Publication Delay')
                )->setDescription(_t(
                    __CLASS__ . '.PublicationDelayDescription',
                    'Delay publiation by the specified number of days'
                ))
            );
        }

        return $fields;
    }

    /**
     * Publish action allows a user who is currently assigned at this point of the workflow to
     *
     * @param  DataObject $target
     * @return bool
     */
    public function canPublishTarget(DataObject $target)
    {
        return true;
    }

    /**
     * @param DataObject|EmbargoExpiryExtension $target
     */
    public function targetRequestsDelayedAction(DataObject $target): bool
    {
        // Delayed actions are only supported if the Embargo & Expiry module is present for the Target
        if (!$this->targetHasEmbargoExpiryModules($target)) {
            // E&E is not present on the target, so it cannot have delayed actions
            return false;
        }

        // Check to see if the Target has requested an embargo or expiry time
        if ($target->getDesiredPublishDateAsTimestamp() > 0
            || $target->getDesiredUnPublishDateAsTimestamp() > 0
        ) {
            // One (or both) have been requested
            return true;
        }

        // For Publish workflow actions, we'll also check if the reviewer set a PublishDelay
        if ($this->PublishDelay) {
            return true;
        }

        // No embargo, expiry, or delay was requested
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

        // Queue UnPublishJob if it's required
        $target->ensureUnPublishJob();

        // Queue PublishJob if it's required. PublishDelay always take priority over DesiredPublishDate, so exit early
        // if we queue a job here
        if ($this->PublishDelay) {
            $target->createOrUpdatePublishJob(strtotime("+{$this->PublishDelay} days"));

            return;
        }

        // Queue PublishJob if it's required
        $target->ensurePublishJob();
    }
}
