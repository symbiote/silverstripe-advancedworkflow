<?php

/**
 * Adds embargo period and expiry dates to content items
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class WorkflowEmbargoExpiryExtension extends DataExtension {
	
	public static $db = array(
		'DesiredPublishDate'	=> 'SS_Datetime',
		'DesiredUnPublishDate'	=> 'SS_Datetime',
		'PublishOnDate'			=> 'SS_Datetime',
		'UnPublishOnDate'		=> 'SS_Datetime',
	);
	
	public static $has_one = array(
		'PublishJob'			=> 'QueuedJobDescriptor',
		'UnPublishJob'			=> 'QueuedJobDescriptor',
	);

	public static $dependencies = array(
		'workflowService'		=> '%$WorkflowService',
	);

	public static $showTimePicker = true;

	/**
	 * @var WorkflowService
	 */
	public $workflowService;
	
	/**
	 * @param FieldList $fields 
	 */
	public function updateCMSFields(FieldList $fields) {

		// Add timepicker functionality
		// @see https://github.com/trentrichardson/jQuery-Timepicker-Addon
		Requirements::css(ADVANCED_WORKFLOW_DIR . '/thirdparty/javascript/jquery-ui/timepicker/jquery-ui-timepicker-addon.css');
		Requirements::css(ADVANCED_WORKFLOW_DIR . '/css/WorkflowFieldTimePicker.css');
		Requirements::javascript(ADVANCED_WORKFLOW_DIR . '/thirdparty/javascript/jquery-ui/timepicker/jquery-ui-sliderAccess.js');
		Requirements::javascript(ADVANCED_WORKFLOW_DIR . '/thirdparty/javascript/jquery-ui/timepicker/jquery-ui-timepicker-addon.js');
		Requirements::javascript(ADVANCED_WORKFLOW_DIR . '/javascript/WorkflowField.js');


		// if there is a workflow applied, we can't set the publishing date directly, only the 'desired'
		// publishing date
		$effective = $this->workflowService->getDefinitionFor($this->owner);
		
		if ($effective) {
			$fields->addFieldsToTab('Root.PublishingSchedule', array(
				new HeaderField('PublishDateHeader', _t('REQUESTED_PUBLISH_DATE_H3', 'Expiry and Embargo'), 3),
				new LiteralField('PublishDateIntro', $this->getIntroMessage('PublishDateIntro')),
				$dt = new Datetimefield('DesiredPublishDate', _t('AdvancedWorkflow.REQUESTED_PUBLISH_DATE', 'Requested publish date and time')),
				$ut = new Datetimefield('DesiredUnPublishDate', _t('AdvancedWorkflow.REQUESTED_UNPUBLISH_DATE', 'Requested un-publish date and time')),
				Datetimefield::create('PublishOnDate', _t('AdvancedWorkflow.PUBLISH_ON', 'Publish date and time'))->setDisabled(true),
				Datetimefield::create('UnPublishOnDate', _t('AdvancedWorkflow.UNPUBLISH_ON', 'Un-publish date and time'))->setDisabled(true),
			));
		} else {
			$fields->addFieldsToTab('Root.PublishingSchedule', array(
				$dt = new Datetimefield('PublishOnDate', _t('AdvancedWorkflow.PUBLISH_ON', 'Publish date and time')),
				$ut = new Datetimefield('UnPublishOnDate', _t('AdvancedWorkflow.UNPUBLISH_ON', 'Un-publish date and time')),
			));
		}

		$dt->getDateField()->setConfig('showcalendar', true);
		$ut->getDateField()->setConfig('showcalendar', true);

		// Enable a jQuery-UI timepicker widget
		if(self::$showTimePicker) {
			$dt->getTimeField()->addExtraClass('hasTimePicker');
			$ut->getTimeField()->addExtraClass('hasTimePicker');
		}
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		// only operate on staging content for this extension; otherwise, you
		// need to publish the page to be able to set a 'future' publish...
		// while the same could be said for the unpublish, the 'publish' state
		// is the one that must be avoided so we allow setting the 'unpublish'
		// date for as-yet-not-published content. 
		if (Versioned::current_stage() != 'Live') {
			
			// check to see if we've got a 'desired' future date. If so, we need
			// to remove any existing values set
			if ($this->owner->DesiredPublishDate && $this->owner->PublishOnDate) {
				$this->owner->PublishOnDate = '';
			}

			if ($this->owner->DesiredUnPublishDate && $this->owner->UnPublishOnDate) {
				$this->owner->UnPublishOnDate = '';
			}

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

	/*
	 * Define an array of message-parts for use by {@link getIntroMessage()}
	 *
	 * @param string $key
	 * @return array
	 */
	public function getIntroMessageParts($key) {
		$parts = array(
			'PublishDateIntro' => array(
				'INTRO'=>_t('WorkflowEmbargoExpiryExtension.REQUESTED_PUBLISH_DATE_INTRO','Enter a date and/or time to specify embargo and expiry dates.'),
				'BULLET_1'=>_t('WorkflowEmbargoExpiryExtension.REQUESTED_PUBLISH_DATE_INTRO_BULLET_1','These settings won\'t take effect until any approval actions are run'),
				'BULLET_2'=>_t('WorkflowEmbargoExpiryExtension.REQUESTED_PUBLISH_DATE_INTRO_BULLET_2','If an embargo is already set, adding a new one prior to that date\'s passing will overwrite it')
			)
		);
		return $parts[$key];
	}

	/*
	 * Display some messages to the user, a little more complex that a simple one-liner
	 *
	 * @param string $key
	 * @return string
	 */
	public function getIntroMessage($key) {
		$msg = $this->getIntroMessageParts($key);
		$curr = Controller::curr();
		$msg = $curr->customise($msg)->renderWith('embargoIntro');
		return $msg;
	}
}
