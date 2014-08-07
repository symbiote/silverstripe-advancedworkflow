<?php

/**
 * Adds embargo period and expiry dates to content items
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class WorkflowEmbargoExpiryExtension extends DataExtension {
	
	private static $db = array(
		'DesiredPublishDate'	=> 'SS_Datetime',
		'DesiredUnPublishDate'	=> 'SS_Datetime',
		'PublishOnDate'			=> 'SS_Datetime',
		'UnPublishOnDate'		=> 'SS_Datetime',
	);

	private static $has_one = array(
		'PublishJob'			=> 'QueuedJobDescriptor',
		'UnPublishJob'			=> 'QueuedJobDescriptor',
	);

	private static $dependencies = array(
		'workflowService'		=> '%$WorkflowService',
	);

	// This "config" option, might better be handled in _config
	public static $showTimePicker = true;

	/**
	 * @var WorkflowService
	 */
	public $workflowService;

	/**
	 * @var Is a workflow in effect?
	 */
	public $isWorkflowInEffect = false;

	/**
	 *
	 * @var array $extendedMethodReturn A basic extended validation routine method return format
	 */
	public static $extendedMethodReturn = array(
		'fieldName'	=>null,
		'fieldField'=>null,
		'fieldMsg'	=>null,
		'fieldValid'=>true
	);
	
	/**
	 * @param FieldList $fields 
	 */
	public function updateCMSFields(FieldList $fields) {
		Requirements::add_i18n_javascript(ADVANCED_WORKFLOW_DIR . '/javascript/lang');

		// Add timepicker functionality
		// @see https://github.com/trentrichardson/jQuery-Timepicker-Addon
		Requirements::css(ADVANCED_WORKFLOW_DIR . '/thirdparty/javascript/jquery-ui/timepicker/jquery-ui-timepicker-addon.css');
		Requirements::css(ADVANCED_WORKFLOW_DIR . '/css/WorkflowCMS.css');
		Requirements::javascript(ADVANCED_WORKFLOW_DIR . '/thirdparty/javascript/jquery-ui/timepicker/jquery-ui-sliderAccess.js');
		Requirements::javascript(ADVANCED_WORKFLOW_DIR . '/thirdparty/javascript/jquery-ui/timepicker/jquery-ui-timepicker-addon.js');
		Requirements::javascript(ADVANCED_WORKFLOW_DIR . '/javascript/WorkflowField.js');

		$this->setIsWorkflowInEffect();

		$fields->findOrMakeTab(
			'Root.PublishingSchedule',
			_t('WorkflowEmbargoExpiryExtension.TabTitle', 'Publishing Schedule')
		);
		if ($this->getIsWorkflowInEffect()) {
			$fields->addFieldsToTab('Root.PublishingSchedule', array(
				new HeaderField('PublishDateHeader', _t('WorkflowEmbargoExpiryExtension.REQUESTED_PUBLISH_DATE_H3', 'Expiry and Embargo'), 3),
				new LiteralField('PublishDateIntro', $this->getIntroMessage('PublishDateIntro')),
				$dt = new Datetimefield('DesiredPublishDate', _t('WorkflowEmbargoExpiryExtension.REQUESTED_PUBLISH_DATE', 'Requested publish date')),
				$ut = new Datetimefield('DesiredUnPublishDate', _t('WorkflowEmbargoExpiryExtension.REQUESTED_UNPUBLISH_DATE', 'Requested un-publish date')),
				Datetimefield::create('PublishOnDate', _t('WorkflowEmbargoExpiryExtension.PUBLISH_ON', 'Scheduled publish date'))->setDisabled(true),
				Datetimefield::create('UnPublishOnDate', _t('WorkflowEmbargoExpiryExtension.UNPUBLISH_ON', 'Scheduled un-publish date'))->setDisabled(true)
			));
		} else {
			$fields->addFieldsToTab('Root.PublishingSchedule', array(
				new HeaderField('PublishDateHeader', _t('WorkflowEmbargoExpiryExtension.REQUESTED_PUBLISH_DATE_H3', 'Expiry and Embargo'), 3),
				new LiteralField('PublishDateIntro', $this->getIntroMessage('PublishDateIntro')),
				$dt = new Datetimefield('PublishOnDate', _t('WorkflowEmbargoExpiryExtension.PUBLISH_ON', 'Scheduled publish date')),
				$ut = new Datetimefield('UnPublishOnDate', _t('WorkflowEmbargoExpiryExtension.UNPUBLISH_ON', 'Scheduled un-publish date')),
			));
		}

		$dt->getDateField()->setConfig('showcalendar', true);
		$ut->getDateField()->setConfig('showcalendar', true);
		$dt->getTimeField()->setConfig('timeformat', 'HH:mm:ss');
		$ut->getTimeField()->setConfig('timeformat', 'HH:mm:ss');

		// Enable a jQuery-UI timepicker widget
		if(self::$showTimePicker) {
			$dt->getTimeField()->addExtraClass('hasTimePicker');
			$ut->getTimeField()->addExtraClass('hasTimePicker');
		}
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		// if we've been duplicated, the old job IDs will be hanging around, so explicitly clear
		if (!$this->owner->ID) {
			$this->owner->PublishJobID = 0;
			$this->owner->UnPublishJobID = 0;
		}

		// only operate on staging content for this extension; otherwise, you
		// need to publish the page to be able to set a 'future' publish...
		// while the same could be said for the unpublish, the 'publish' state
		// is the one that must be avoided so we allow setting the 'unpublish'
		// date for as-yet-not-published content. 
		if (Versioned::current_stage() != 'Live') {
			
			/*
			 * Without checking if there's actually a workflow in effect, simply saving
			 * as draft, would clear the Scheduled Publish & Unpublish date fields, which we obviously
			 * don't want during a workflow: These date fields should be treated as a content
			 * change that also requires approval (where such an approval step exists).
			 *
			 * - Check to see if we've got 'desired' publish/unpublish date(s).
			 * - Check if there's a workflow attached to this content
			 * - Reset values if it's safe to do so
			 */
			$resetPublishOnDate = $this->owner->DesiredPublishDate && $this->owner->PublishOnDate;
			if ($resetPublishOnDate && !$this->getIsWorkflowInEffect()) {
				$this->owner->PublishOnDate = '';
			}

			$resetUnPublishOnDate = $this->owner->DesiredUnPublishDate && $this->owner->UnPublishOnDate;
			if ($resetUnPublishOnDate && !$this->getIsWorkflowInEffect()) {
				$this->owner->UnPublishOnDate = '';
			}

			// Jobs can only be queued for records that already exist
			if($this->owner->ID) {
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
		// If there's no effective workflow, no need for the first bullet-point
		if(!$this->getIsWorkflowInEffect()) {
			$parts['PublishDateIntro']['BULLET_1'] = false;
		}
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

	/*
	 * Validate
	 */
	public function getCMSValidator() {
		$required = new AWRequiredFields();
		$required->setCaller($this);
		return $required;
	}

	/**
	 *
	 * Uses AWRequiredFields to peform validation at the DataExtension level. 
	 *
	 * @see {@ink AWRequiredFields}
	 * @param array $data
	 * @return array
	 */
	public function extendedRequiredFieldsCheckEmbargoDates($data = null) {
		if(!$this->getIsWorkflowInEffect()) {
			return self::$extendedMethodReturn;
		}

		$desiredEmbargo = false;
		$desiredExpiry = false;

		if(!empty($data['DesiredPublishDate'])) {
			$desiredEmbargo = strtotime($data['DesiredPublishDate']);
		}

		if(!empty($data['DesiredPublishDate'])) {
			$desiredExpiry = strtotime($data['DesiredUnPublishDate']);
		}

		$msg = '';

		if($desiredEmbargo && $desiredEmbargo < time()) {
			$msg = _t(
				'EMBARGO_DSIRD_ERROR',
				"This date has already passed, please enter a valid future date."
			);
			self::$extendedMethodReturn['fieldName'] = 'DesiredPublishDate';
		}
		
		if($desiredExpiry && $desiredExpiry < time()) {
			$msg = _t(
				'EMBARGO_DSIRD_ERROR',
				"This date has already passed, please enter a valid future date."
			);
			self::$extendedMethodReturn['fieldName'] = 'DesiredUnPublishDate';
		}
		
		if(strlen($msg)) {
			self::$extendedMethodReturn['fieldValid'] = false;
			self::$extendedMethodReturn['fieldMsg'] = $msg;
		}
		
		return self::$extendedMethodReturn;
	}

	/*
	 * Format a date according to member/user preferences
	 *
	 * @param string $date
	 * @return string $date
	 */
	public function getUserDate($date) {
		$date = new Zend_Date($date);
		$member = Member::currentUser();
		return $date->toString($member->getDateFormat().' '.$member->getTimeFormat());
	}

	/*
	 * Sets property as boolean true|false if an effective workflow is found or not
	 */
	public function setIsWorkflowInEffect() {
		// if there is a workflow applied, we can't set the publishing date directly, only the 'desired' publishing date
		$effective = $this->workflowService->getDefinitionFor($this->owner);
		$this->isWorkflowInEffect = $effective?true:false;
	}

	public function getIsWorkflowInEffect() {
		return $this->isWorkflowInEffect;
	}
}