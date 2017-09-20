<?php

use SilverStripe\ORM\DataObject;

/**
 * Publishes an item
 *
 * @author     marcus@symbiote.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage actions
 */
class PublishItemWorkflowAction extends WorkflowAction
{

    private static $db = array(
        'PublishDelay'          => 'Int',
        'AllowEmbargoedEditing' => 'Boolean',
    );

    private static $defaults = array(
        'AllowEmbargoedEditing' => true
    );

    private static $icon = 'advancedworkflow/images/publish.png';

    public function execute(WorkflowInstance $workflow)
    {
        if (!$target = $workflow->getTarget()) {
            return true;
        }

        if (class_exists('AbstractQueuedJob') && $this->PublishDelay) {
            $job   = new WorkflowPublishTargetJob($target);
            $days  = $this->PublishDelay;
            $after = date('Y-m-d H:i:s', strtotime("+$days days"));

            // disable editing, and embargo the delay if using WorkflowEmbargoExpiryExtension
            if ($target->hasExtension('WorkflowEmbargoExpiryExtension')) {
                $target->AllowEmbargoedEditing = $this->AllowEmbargoedEditing;
                $target->PublishOnDate = $after;
                $target->write();
            } else {
                singleton('QueuedJobService')->queueJob($job, $after);
            }
        } elseif ($target->hasExtension('WorkflowEmbargoExpiryExtension')) {
            $target->AllowEmbargoedEditing = $this->AllowEmbargoedEditing;
            // setting future date stuff if needbe

            // set this value regardless
            $target->UnPublishOnDate = $target->DesiredUnPublishDate;
            $target->DesiredUnPublishDate = '';
            if ($target->DesiredPublishDate) {
                $target->PublishOnDate = $target->DesiredPublishDate;
                $target->DesiredPublishDate = '';
                $target->write();
            } else {
                if ($target->hasMethod('doPublish')) {
                    $target->doPublish();
                }
            }
        } else {
            if ($target->hasMethod('doPublish')) {
                $target->doPublish();
            }
        }

        return true;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        if (class_exists('AbstractQueuedJob')) {
            $before = _t('PublishItemWorkflowAction.DELAYPUBDAYSBEFORE', 'Delay publication ');
            $after  = _t('PublishItemWorkflowAction.DELAYPUBDAYSAFTER', ' days');
            $allowEmbargoed =  _t(
                'PublishItemWorkflowAction.ALLOWEMBARGOEDEDITING',
                'Allow editing while item is embargoed? (does not apply without embargo)'
            );

            $fields->addFieldsToTab('Root.Main', array(
                new CheckboxField('AllowEmbargoedEditing', $allowEmbargoed),
                new FieldGroup(
                    _t('PublishItemWorkflowAction.PUBLICATIONDELAY', 'Publication Delay'),
                    new LabelField('PublishDelayBefore', $before),
                    new NumericField('PublishDelay', ''),
                    new LabelField('PublishDelayAfter', $after)
                ),
            ));
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
}
