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
	 * Is a workflow in effect?
	 *
	 * @var bool
	 */
	public $isWorkflowInEffect = false;

	/**
	 * A basic extended validation routine method return format
	 *
	 * @var array
	 */
	public static $extendedMethodReturn = array(
		'fieldName'	=>null,
		'fieldField'=>null,
		'fieldMsg'	=>null,
		'fieldValid'=>true
	);

    public function __construct() {
        // Queued jobs descriptor is required for this extension
        if (class_exists('QueuedJobDescriptor')) {
            return parent::__construct();
        }
    }

	/**
	 * @param FieldList $fields
	 */
	public function updateCMSFields(FieldList $fields) {

	    // requirements
	    // ------------

		Requirements::add_i18n_javascript(ADVANCED_WORKFLOW_DIR . '/javascript/lang');

		// Add timepicker functionality
		// @see https://github.com/trentrichardson/jQuery-Timepicker-Addon
		Requirements::css(
			ADVANCED_WORKFLOW_DIR . '/thirdparty/javascript/jquery-ui/timepicker/jquery-ui-timepicker-addon.css'
		);
		Requirements::css(ADVANCED_WORKFLOW_DIR . '/css/WorkflowCMS.css');
		Requirements::javascript(
			ADVANCED_WORKFLOW_DIR . '/thirdparty/javascript/jquery-ui/timepicker/jquery-ui-sliderAccess.js'
		);
		Requirements::javascript(
			ADVANCED_WORKFLOW_DIR . '/thirdparty/javascript/jquery-ui/timepicker/jquery-ui-timepicker-addon.js'
		);
		Requirements::javascript(ADVANCED_WORKFLOW_DIR . '/javascript/WorkflowField.js');

        // Fields
        // ------

		// we never show these explicitly in admin
		$fields->removeByName('PublishJobID');
		$fields->removeByName('UnPublishJobID');

		$this->setIsWorkflowInEffect();

		$fields->findOrMakeTab(
			'Root.PublishingSchedule',
			_t('WorkflowEmbargoExpiryExtension.TabTitle', 'Publishing Schedule')
		);
		if ($this->getIsWorkflowInEffect()) {

			// add fields we want in this context
			$fields->addFieldsToTab('Root.PublishingSchedule', array(
				HeaderField::create(
					'PublishDateHeader',
					_t('WorkflowEmbargoExpiryExtension.REQUESTED_PUBLISH_DATE_H3', 'Expiry and Embargo'),
					3
				),
				LiteralField::create('PublishDateIntro', $this->getIntroMessage('PublishDateIntro')),
				$dt = Datetimefield::create(
					'DesiredPublishDate',
					_t('WorkflowEmbargoExpiryExtension.REQUESTED_PUBLISH_DATE', 'Requested publish date')
				),
				$ut = Datetimefield::create(
					'DesiredUnPublishDate',
					_t('WorkflowEmbargoExpiryExtension.REQUESTED_UNPUBLISH_DATE', 'Requested un-publish date')
				),
				Datetimefield::create(
					'PublishOnDate',
					_t('WorkflowEmbargoExpiryExtension.PUBLISH_ON', 'Scheduled publish date')
				)->setDisabled(true),
				Datetimefield::create(
					'UnPublishOnDate',
					_t('WorkflowEmbargoExpiryExtension.UNPUBLISH_ON', 'Scheduled un-publish date')
				)->setDisabled(true)
			));
		} else {

		    // remove fields that have been automatically added that we don't want
			$fields->removeByName('DesiredPublishDate');
			$fields->removeByName('DesiredUnPublishDate');

			// add fields we want in this context
			$fields->addFieldsToTab('Root.PublishingSchedule', array(
				HeaderField::create(
					'PublishDateHeader',
					_t('WorkflowEmbargoExpiryExtension.REQUESTED_PUBLISH_DATE_H3', 'Expiry and Embargo'),
					3
				),
				LiteralField::create('PublishDateIntro', $this->getIntroMessage('PublishDateIntro')),
				$dt = Datetimefield::create(
					'PublishOnDate',
					_t('WorkflowEmbargoExpiryExtension.PUBLISH_ON', 'Scheduled publish date')
				),
				$ut = Datetimefield::create(
					'UnPublishOnDate',
					_t('WorkflowEmbargoExpiryExtension.UNPUBLISH_ON', 'Scheduled un-publish date')
				),
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

	/**
	 * Clears any existing publish job against this dataobject
	 */
	protected function clearPublishJob() {
		$job = $this->owner->PublishJob();
		if($job && $job->exists()) {
			$job->delete();
		}
		$this->owner->PublishJobID = 0;
	}

	/**
	 * Clears any existing unpublish job
	 */
	protected function clearUnPublishJob() {
		// Cancel any in-progress unpublish job
		$job = $this->owner->UnPublishJob();
		if ($job && $job->exists()) {
			$job->delete();
		}
		$this->owner->UnPublishJobID = 0;
	}

	/**
	 * Ensure the existence of a publish job at the specified time
	 *
	 * @param int $when Timestamp to start this job, or null to start immediately
	 */
	protected function ensurePublishJob($when) {
		// Check if there is a prior job
		if($this->owner->PublishJobID) {
			$job = $this->owner->PublishJob();
			// Use timestamp for sake of comparison.
			if($job && $job->exists() && strtotime($job->StartAfter) == $when) {
				return;
			}
			$this->clearPublishJob();
		}

		// Create a new job with the specified schedule
		$job = new WorkflowPublishTargetJob($this->owner, 'publish');
		$this->owner->PublishJobID = Injector::inst()->get('QueuedJobService')
				->queueJob($job, $when ? date('Y-m-d H:i:s', $when) : null);
	}

	/**
	 * Ensure the existence of an unpublish job at the specified time
	 *
	 * @param int $when Timestamp to start this job, or null to start immediately
	 */
	protected function ensureUnPublishJob($when) {
		// Check if there is a prior job
		if($this->owner->UnPublishJobID) {
			$job = $this->owner->UnPublishJob();
			// Use timestamp for sake of comparison.
			if($job && $job->exists() && strtotime($job->StartAfter) == $when) {
				return;
			}
			$this->clearUnPublishJob();
		}

		// Create a new job with the specified schedule
		$job = new WorkflowPublishTargetJob($this->owner, 'unpublish');
		$this->owner->UnPublishJobID = Injector::inst()->get('QueuedJobService')
			->queueJob($job, $when ? date('Y-m-d H:i:s', $when) : null);
	}

	/**
	 * {@see PublishItemWorkflowAction} for approval of requested publish dates
	 */
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
		if (Versioned::get_stage() === Versioned::LIVE) {
            return;
        }

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
		if(!$this->owner->ID) return;

		// Check requested dates of publish / unpublish, and whether the page should have already been unpublished
		$now = strtotime(SS_Datetime::now()->getValue());
		$publishTime = strtotime($this->owner->PublishOnDate);
		$unPublishTime = strtotime($this->owner->UnPublishOnDate);

		// We should have a publish job if:
		if($publishTime && ( // We have a date
			$unPublishTime < $publishTime // it occurs after an unpublish date (or there is no unpublish)
			|| $unPublishTime > $now // or the unpublish date hasn't passed
		)) {
			// Trigger time immediately if passed
			$this->ensurePublishJob($publishTime < $now ? null : $publishTime);
		} else {
			$this->clearPublishJob();
		}

		// We should have an unpublish job if:
		if($unPublishTime && ( // we have a date
			$publishTime < $unPublishTime // it occurs after a publish date (or there is no publish)
			|| $publishTime > $now // or the publish date hasn't passed
		)) {
			// Trigger time immediately if passed
			$this->ensureUnPublishJob($unPublishTime < $now ? null : $unPublishTime);
		} else {
			$this->clearUnPublishJob();
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

    /**
     * Returns whether a publishing date has been set and is after the current date
     *
     * @return bool
     */
    public function getIsPublishScheduled()
    {
        if (!$this->owner->PublishOnDate) {
            return false;
        }
        $now = strtotime(SS_Datetime::now()->getValue());
        $publish = strtotime($this->owner->PublishOnDate);

        return $now < $publish;
    }

    /**
     * Returns whether an unpublishing date has been set and is after the current date
     *
     * @return bool
     */
    public function getIsUnPublishScheduled()
    {
        if (!$this->owner->UnPublishOnDate) {
            return false;
        }
        $now = strtotime(SS_Datetime::now()->getValue());
        $unpublish = strtotime($this->owner->UnPublishOnDate);

        return $now < $unpublish;
    }

    /**
     * Get any future time set in GET param. Recommended to use ISO-8601 for url readability.
     *
     * @return string Time in format useful for SQL comparison.
     */
    public function getFutureTime()
    {
        $time = null;
        $curr = Controller::has_curr() ? Controller::curr() : false;

        if ($curr) {
            $ft = $curr->getRequest()->getVar('ft');
            if ($ft) {
                // Convert some characters
                $time = date('Y-m-d H:i', strtotime($ft));
            }
        }
        return $time;
    }

    /**
     * Get link for a future date and time. Resulting format is ISO-8601 compliant. As the underlying
     * SQL query for future state relies on versioning the link is only returned if Versioned extension
     * is applied.
     *
     * @param  string $futureTime Date that can be parsed by strtotime
     * @return string|null        Either the URL with future time added or null if time cannot be parsed
     */
    public function getFutureTimeLink($futureTime)
    {
        $parsed = strtotime($futureTime);
        if ($parsed && $this->owner->has_extension('Versioned')) {
            return Controller::join_links(
                $this->owner->PreviewLink(),
                '?stage=Stage',
                '?ft=' . date('Ymd\THi', $parsed)
            );
        }
    }

    /**
     * Set future time flag on the query for further queries to use. Only set if Versioned
     * extension is applied as the query relies on _versions tables.
     */
    public function augmentDataQueryCreation(SQLSelect &$query, DataQuery &$dataQuery)
    {
        // If time is set then flag it up for queries
        $time = $this->getFutureTime();
        if ($time && $this->owner->has_extension('Versioned')) {
            $dataQuery->setQueryParam('Future.time', $time);
        }
    }

    /**
     * Alter SQL queries for this object so that the version matching the time that is passed is returned.
     * Relies on Versioned extension as it queries the _versions table and is only triggered when viewing the staging
     * site e.g: ?stage=Stage. This has the side effect that Versioned::canViewVersioned() is used to restrict
     * access.
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        $time = $dataQuery->getQueryParam('Future.time');

        if (!$time || !$this->owner->has_extension('Versioned')) {
            return;
        }

        // Only trigger future state when viewing "Stage", this ensures the query works with Versioned::augmentSQL()
        $stage = $dataQuery->getQueryParam('Versioned.stage');
        if ($stage === Versioned::DRAFT) {
            $baseTable = ClassInfo::baseDataClass($dataQuery->dataClass());

            foreach($query->getFrom() as $alias => $join) {

                if (!class_exists($alias) || !is_a($alias, $baseTable, true)) {
                    continue;
                }

                if($alias != $baseTable) {
                    // Make sure join includes version as well
                    $query->setJoinFilter(
                        $alias,
                        "\"{$alias}_versions\".\"RecordID\" = \"{$baseTable}_versions\".\"RecordID\""
                        . " AND \"{$alias}_versions\".\"Version\" = \"{$baseTable}_versions\".\"Version\""
                    );
                }
                $query->renameTable($alias, $alias . '_versions');
            }

            // Add all <basetable>_versions columns
            foreach(Config::inst()->get('Versioned', 'db_for_versions_table') as $name => $type) {
                $query->selectField(sprintf('"%s_versions"."%s"', $baseTable, $name), $name);
            }

            // Alias the record ID as the row ID, and ensure ID filters are aliased correctly
            $query->selectField("\"{$baseTable}_versions\".\"RecordID\"", "ID");
            $query->replaceText("\"{$baseTable}_versions\".\"ID\"", "\"{$baseTable}_versions\".\"RecordID\"");

            // However, if doing count, undo rewrite of "ID" column
            $query->replaceText(
                "count(DISTINCT \"{$baseTable}_versions\".\"RecordID\")",
                "count(DISTINCT \"{$baseTable}_versions\".\"ID\")"
            );

            /*
             * Querying the _versions table to find the most recent draft or published record that would be published at
             * the time requested. When embargo is NULL it is assumed that the record is published immediately. When
             * expiry is NULL it is assumed that the record is never unpublished.
             */
            $query->addWhere([
                "\"{$baseTable}_versions\".\"Version\" IN
                (SELECT LatestVersion FROM
                    (SELECT
                        \"{$baseTable}_versions\".\"RecordID\",
                        MAX(\"{$baseTable}_versions\".\"Version\") AS LatestVersion
                        FROM \"{$baseTable}_versions\"
                        WHERE
                            /*
                             * Get the latest draft version where the embargo and expiry encompass the time requested, the draft has been approved
                             * for publishing and it is currently waiting in the queue. It must be the latest draft version and more recent than
                             * the latest live version. It also must have a matching record in the basetable to ensure it has not been archived.
                             */
                            (
                                (\"{$baseTable}_versions\".\"PublishOnDate\" <= ? OR \"{$baseTable}_versions\".\"PublishOnDate\" IS NULL)
                                AND
                                (\"{$baseTable}_versions\".\"UnPublishOnDate\" >= ? OR \"{$baseTable}_versions\".\"UnPublishOnDate\" IS NULL)
                                AND
                                \"{$baseTable}_versions\".\"WasPublished\" = 0
                                AND
                                (\"{$baseTable}_versions\".\"PublishJobID\" != 0)
                                AND
                                \"{$baseTable}_versions\".\"Version\" IN (
                                    SELECT MAX(\"LatestDrafts\".\"Version\") AS LatestDraftVersion
                                    FROM \"{$baseTable}_versions\" AS LatestDrafts
                                    INNER JOIN \"{$baseTable}\" AS Base ON \"Base\".\"ID\" = \"LatestDrafts\".\"RecordID\"
                                    WHERE \"LatestDrafts\".\"RecordID\" = \"{$baseTable}_versions\".\"RecordID\"
                                    AND \"Base\".\"ID\" IS NOT NULL
                                    AND \"LatestDrafts\".\"WasPublished\" = 0
                                    AND \"LatestDrafts\".\"Version\" > (
                                        SELECT CASE WHEN COUNT(1) > 0 THEN MAX(Version) ELSE 0 END AS LatestPublishedVersion
                                        FROM \"{$baseTable}_versions\" AS LatestPublished
                                        WHERE \"LatestPublished\".\"RecordID\" = \"{$baseTable}_versions\".\"RecordID\"
                                        AND \"LatestPublished\".\"WasPublished\" = 1
                                    )
                                )
                            )
                            /*
                             * If no draft records match then look for a live record where expiry is greater than the time requested, the record was
                             * published and the record is the most recent published. It also must have a matching record in basetable_Live to ensure
                             * it has not been unpublished.
                             */
                            OR
                            (
                                (\"{$baseTable}_versions\".\"UnPublishOnDate\" >= ? OR \"{$baseTable}_versions\".\"UnPublishOnDate\" IS NULL)
                                AND
                                \"{$baseTable}_versions\".\"WasPublished\" = 1
                                AND
                                \"{$baseTable}_versions\".\"Version\" IN (
                                    SELECT MAX(\"LatestPublished\".\"Version\") AS LatestPublishedVersion
                                    FROM \"{$baseTable}_versions\" AS LatestPublished
                                    INNER JOIN \"{$baseTable}_Live\" ON \"{$baseTable}_Live\".\"ID\" = \"LatestPublished\".\"RecordID\"
                                    WHERE \"LatestPublished\".\"RecordID\" = \"{$baseTable}_versions\".\"RecordID\"
                                    AND \"{$baseTable}_Live\".\"ID\" IS NOT NULL
                                    AND \"LatestPublished\".\"WasPublished\" = 1
                                )
                                AND
                                /*
                                 * There is no draft that is waiting in the queue and will be published prior to the date requested which will
                                 * replace the current live record.
                                 */
                                \"{$baseTable}_versions\".\"Version\" >= (
                                    SELECT  CASE WHEN COUNT(1) > 0 THEN \"Base\".\"Version\" ELSE 0 END AS LatestDraftVersion
                                    FROM \"{$baseTable}\" AS Base
                                    WHERE \"Base\".\"ID\" = \"{$baseTable}_versions\".\"RecordID\"
                                    AND \"Base\".\"PublishOnDate\" <= ? OR \"Base\".\"PublishOnDate\" IS NULL
                                    AND \"Base\".\"PublishJobID\" != 0
                                )
                            )
                        GROUP BY \"{$baseTable}_versions\".\"RecordID\"
                    ) AS \"{$baseTable}_versions_latest\"
                    WHERE \"{$baseTable}_versions_latest\".\"RecordID\" = \"{$baseTable}_versions\".\"RecordID\"
                )"
                => [$time, $time, $time, $time]
            ]);

            // Hack to address the issue of replacing {$baseTable} with {$baseTable}_versions everywhere in the query,
            // there are places where we do want to use {$baseTable}
            $query->replaceText(
                "\"{$baseTable}_versions\" AS Base",
                "\"{$baseTable}\" AS Base"
            );
        }
    }
}
