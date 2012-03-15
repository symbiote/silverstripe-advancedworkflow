<?php

/**
 * Adds embargo period and expiry dates to content items
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class WorkflowEmbargoExpiryExtension extends DataObjectDecorator {
	
	public function extraStatics() {
		return array(
			'db'			=> array(
				'PublishOnDate'			=> 'SS_Datetime',
				'UnPublishOnDate'		=> 'SS_Datetime',
			),
			'has_one'		=> array(
				'PublishJob'			=> 'QueuedJobDescriptor',
				'UnPublishJob'			=> 'QueuedJobDescriptor',
			),
		);
	}

	/**
	 * @param FieldSet $fields 
	 */
	public function updateCMSFields($fields) {
		$fields->addFieldsToTab('Root.Content.PublishingSchedule', array(
			$dt = new Datetimefield('PublishOnDate', _t('AdvancedWorkflow.PUBLISH_ON', 'Publish on')),
			$ut = new Datetimefield('UnPublishOnDate', _t('AdvancedWorkflow.UNPUBLISH_ON', 'Un-publish on')),
		));

		$dt->getDateField()->setConfig('showcalendar', true);
		$dt->getTimeField()->setConfig('showdropdown', true);
		$ut->getDateField()->setConfig('showcalendar', true);
		$ut->getTimeField()->setConfig('showdropdown', true);
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		// only operate on staging content 
		if (Versioned::current_stage() != 'Live') {
			if (strlen($this->owner->PublishOnDate)) {
				$changed = $this->owner->getChangedFields();
				$changed = isset($changed['PublishOnDate']);

				if ($changed && $this->owner->PublishJobID) {
					if ($this->owner->PublishJob()->exists()) {
						$this->owner->PublishJob()->delete();
					}
					$this->owner->PublishJobID = 0;
				}

				if (!$this->owner->PublishJobID && strtotime($this->owner->PublishOnDate) > time()) {
					$job = new WorkflowPublishTargetJob($this->owner, 'publish');
					$this->owner->PublishJobID = singleton('QueuedJobService')->queueJob($job, $this->owner->PublishOnDate);
				}
			}

			if (strlen($this->owner->UnPublishOnDate)) {
				$changed = $this->owner->getChangedFields();
				$changed = isset($changed['UnPublishOnDate']);

				if ($changed && $this->owner->UnPublishJobID) {
					if ($this->owner->UnPublishJob()->exists()) {
						$this->owner->UnPublishJob()->delete();
					}
					$this->owner->UnPublishJobID = 0;
				}

				if (!$this->owner->UnPublishJobID && strtotime($this->owner->UnPublishOnDate) > time()) {
					$job = new WorkflowPublishTargetJob($this->owner, 'unpublish');
					$this->owner->UnPublishJobID = singleton('QueuedJobService')->queueJob($job, $this->owner->UnPublishOnDate);
				}
			}
		}
	}
}
