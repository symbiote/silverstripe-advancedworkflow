<?php
/**
 * Publishes an item
 *
 * @author     marcus@silverstripe.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage actions
 */
class PublishItemWorkflowAction extends WorkflowAction {

	private static $db = array(
		'PublishDelay' => 'Int'
	);

	public static $icon = 'advancedworkflow/images/publish.png';

	public function execute(WorkflowInstance $workflow) {

		// Hoist
		$doPublish = true;

		// Target
		if (!$target = $workflow->getTarget()) {
			return true;
		}

		if (class_exists('AbstractQueuedJob') && $this->PublishDelay) {
			$version = null;
			if ($target->hasField('Version')) {
				$version = $target->getField('Version');
			}
			$job   = new WorkflowPublishTargetJob($target, 'publish', $version);
			$days  = $this->PublishDelay;
			$after = date('Y-m-d H:i:s', strtotime("+$days days"));
			singleton('QueuedJobService')->queueJob($job, $after);
			$doPublish = false;
		} else if ($target->hasExtension('WorkflowEmbargoExpiryExtension')) {
			// Schedule Publishing/Unpublishing dates

			// Always hand-off unpublish date
			$target->UnPublishOnDate = $target->DesiredUnPublishDate;
			$target->DesiredUnPublishDate = '';

			// Publish dates
			if ($target->DesiredPublishDate) {

				// Hand-off desired publish date
				$target->PublishOnDate = $target->DesiredPublishDate;
				$target->DesiredPublishDate = '';
				$target->write();
			}

			// Always check publish date
			if ($target->PublishOnDate) {

				// Check publish date
				$now = strtotime(SS_Datetime::now()->getValue());
				if (strtotime($target->PublishOnDate) > $now) {
					$doPublish = false;
				}
			}
		}

		// Check whether to publish
		if ($doPublish) {
			if ($target->hasMethod('doPublish')) {
				$target->doPublish();
			} else if ($target->hasMethod('publish')) {
				$target->publish('Stage', 'Live');
			}
		}

		return true;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		if (class_exists('AbstractQueuedJob')) {
			$before = _t('PublishItemWorkflowAction.DELAYPUBDAYSBEFORE', 'Delay publication ');
			$after  = _t('PublishItemWorkflowAction.DELAYPUBDAYSAFTER', ' days');

			$fields->addFieldToTab('Root.Main', new FieldGroup(
				_t('PublishItemWorkflowAction.PUBLICATIONDELAY', 'Publication Delay'),
				new LabelField('PublishDelayBefore', $before),
				new NumericField('PublishDelay', ''),
				new LabelField('PublishDelayAfter', $after)
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
	public function canPublishTarget(DataObject $target) {
		return true;
	}

}
