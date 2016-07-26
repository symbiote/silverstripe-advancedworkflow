<?php

use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Versioning\Versioned;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

// Queued jobs descriptor is required for this extension
if (!class_exists('QueuedJobDescriptor')) {
    return;
}

/**
 * Adds embargo period and expiry dates to content items
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class WorkflowEmbargoExpiryExtension extends DataExtension {

    /**
     * For storing future time from request when request object is replaced
     * within a single request e.g: ErrorPage::response_for()
     *
     * @var null|string The future time
     */
    private static $future_time = null;

	private static $db = array(
		'DesiredPublishDate'	=> 'DBDatetime',
		'DesiredUnPublishDate'	=> 'DBDatetime',
		'PublishOnDate'			=> 'DBDatetime',
		'UnPublishOnDate'		=> 'DBDatetime',
        'AllowEmbargoedEditing' => 'Boolean',
	);

	private static $has_one = array(
		'PublishJob'			=> 'QueuedJobDescriptor',
		'UnPublishJob'			=> 'QueuedJobDescriptor',
	);

	private static $dependencies = array(
		'workflowService'		=> '%$WorkflowService',
	);

    private static $defaults = array(
        'AllowEmbargoedEditing' => true
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

	/**
	 * @param FieldList $fields
	 */
	public function updateCMSFields(FieldList $fields) {

	    // Requirements
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

        // Fields: Publishing Schedule
        // ---------------------------

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
				)->setRightTitle(
                    LiteralField::create(
                        'DesiredPublishDateRightTitle',
                        _t('WorkflowEmbargoExpiryExtension.REQUESTED_PUBLISH_DATE_RIGHT_TITLE', 'To request this page to be <strong>published immediately</strong> leave the date and time fields blank')
                    )
                ),
				$ut = Datetimefield::create(
					'DesiredUnPublishDate',
					_t('WorkflowEmbargoExpiryExtension.REQUESTED_UNPUBLISH_DATE', 'Requested un-publish date')
				)->setRightTitle(
                    LiteralField::create(
                        'DesiredUnPublishDateRightTitle',
                        _t('WorkflowEmbargoExpiryExtension.REQUESTED_UNPUBLISH_DATE_RIGHT_TITLE', 'To request this page to <strong>never expire</strong> leave the date and time fields blank')
                    )
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

        // add future state preview fields to easily browse different future state dates
        $fields->addFieldsToTab('Root.PublishingSchedule', array(
            HeaderField::create(
                'FuturePreviewHeader',
                _t('WorkflowEmbargoExpiryExtension.FUTURE_PREVIEW_HEADER', 'Preview Future State'),
                3
            ),
            $ft = FutureStatePreviewField::create(
                'FuturePreviewDate',
                _t('WorkflowEmbargoExpiryExtension.FUTURE_PREVIEW_DATE', 'Set preview date')
            ),
        ));

		$dt->getDateField()->setConfig('showcalendar', true);
        $ut->getDateField()->setConfig('showcalendar', true);
        $ft->getDateField()->setConfig('showcalendar', true);
		$dt->getTimeField()->setConfig('timeformat', 'HH:mm');
        $ut->getTimeField()->setConfig('timeformat', 'HH:mm');
        $ft->getTimeField()->setConfig('timeformat', 'HH:mm');

		// Enable a jQuery-UI timepicker widget
		if (self::$showTimePicker) {
			$dt->getTimeField()->addExtraClass('hasTimePicker');
            $ut->getTimeField()->addExtraClass('hasTimePicker');
            $ft->getTimeField()->addExtraClass('hasTimePicker');
		}

        // Fields: Status message
        // ----------------------
        if ($this->getEmbargoExpiryStatuses()) {
            $message = LiteralField::create('WorkflowStatusMessage', $this->renderEmbargoExpiryMessages());

            $tab = $fields->findOrMakeTab('Root.Main');
            $tab->unshift($message);
        }
	}

	/**
	 * Clears any existing publish job against this dataobject
	 */
	public function clearPublishJob() {
		$job = $this->owner->PublishJob();
		if($job && $job->exists()) {
			$job->delete();
		}
		$this->owner->PublishJobID = 0;
	}

	/**
	 * Clears any existing unpublish job
	 */
    public function clearUnPublishJob() {
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

    public function onBeforeDuplicate($original, $doWrite) {
        $clone = $this->owner;

        $clone->PublishOnDate = '';
        $clone->UnPublishOnDate = '';
        $clone->PublishJobID = 0;
        $clone->UnPublishJobID = 0;
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
        if (!$this->getIsWorkflowInEffect()) {
            $resetPublishOnDate = $this->owner->DesiredPublishDate && $this->owner->PublishOnDate;
            if ($resetPublishOnDate) {
                $this->owner->PublishOnDate = '';
            }

            $resetUnPublishOnDate = $this->owner->DesiredUnPublishDate && $this->owner->UnPublishOnDate;
            if ($resetUnPublishOnDate) {
                $this->owner->UnPublishOnDate = '';
            }
        }

		// Jobs can only be queued for records that already exist
		if(!$this->owner->ID) return;

		// Check requested dates of publish / unpublish, and whether the page should have already been unpublished
		$now = strtotime(DBDatetime::now()->getValue());
		$publishTime = strtotime($this->owner->PublishOnDate);
		$unPublishTime = strtotime($this->owner->UnPublishOnDate);

		// We should have a publish job if:
		if($publishTime && ( // We have a date
			$unPublishTime < $publishTime // it occurs before an unpublish date (or there is no unpublish)
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

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        /*
         * Make sure this is for SiteTree first..
         * Can make a copy of this for other classes (in a separate extension) that may depend on Sorting as well
         */
        if ($this->owner instanceof SiteTree) {
            // Update the latest version for each record with the correct Sort value because LeftAndMain::savetreenode()
            // only updates the SiteTree table. We rely on SiteTree_versions in augmentSQL() for futurestate.
            DB::prepared_query("
                UPDATE \"SiteTree_versions\"
                SET \"Sort\" = (
                    SELECT \"SiteTree\".\"Sort\"
                    FROM \"SiteTree\"
                    WHERE  \"SiteTree\".\"ID\" = \"SiteTree_versions\".\"RecordID\"
                    AND \"SiteTree_versions\".\"Version\" = \"SiteTree\".\"Version\"
                    AND \"SiteTree_versions\".\"ParentID\" = ?
                )
                WHERE EXISTS (
                    SELECT *
                    FROM \"SiteTree\"
                    WHERE  \"SiteTree\".\"ID\" = \"SiteTree_versions\".\"RecordID\"
                    AND \"SiteTree_versions\".\"Version\" = \"SiteTree\".\"Version\"
                    AND \"SiteTree_versions\".\"ParentID\" = ?
                )",
                array($this->owner->ParentID, $this->owner->ParentID)
            );
        }
    }

    /**
     * Add badges to the site tree view to show that a page has been scheduled for publishing or unpublishing
     *
     * @param $flags
     */
    public function updateStatusFlags(&$flags)
    {
        $embargo = $this->getIsPublishScheduled();
        $expiry = $this->getIsUnPublishScheduled();

        if ($embargo || $expiry) {
            unset($flags['addedtodraft'], $flags['modified']);
        }

        if ($embargo && $expiry) {
            $flags['embargo_expiry'] = array(
                'text' => _t('WorkflowEmbargoExpiryExtension.BADGE_PUBLISH_UNPUBLISH', 'Embargo+Expiry'),
                'title' => sprintf('%s: %s, %s: %s',
                    _t('WorkflowEmbargoExpiryExtension.PUBLISH_ON', 'Scheduled publish date'),
                    $this->owner->PublishOnDate,
                    _t('WorkflowEmbargoExpiryExtension.UNPUBLISH_ON', 'Scheduled un-publish date'),
                    $this->owner->UnPublishOnDate
                ),
            );
        }
        elseif ($embargo) {
            $flags['embargo'] = array(
                'text' => _t('WorkflowEmbargoExpiryExtension.BADGE_PUBLISH', 'Embargo'),
                'title' => sprintf('%s: %s',
                    _t('WorkflowEmbargoExpiryExtension.PUBLISH_ON', 'Scheduled publish date'),
                    $this->owner->PublishOnDate
                ),
            );
        }
        elseif ($expiry) {
            $flags['expiry'] = array(
                'text' => _t('WorkflowEmbargoExpiryExtension.BADGE_UNPUBLISH', 'Expiry'),
                'title' => sprintf('%s: %s',
                    _t('WorkflowEmbargoExpiryExtension.UNPUBLISH_ON', 'Scheduled un-publish date'),
                    $this->owner->UnPublishOnDate
                ),
            );
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
        $now = strtotime(DBDatetime::now()->getValue());
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
        $now = strtotime(DBDatetime::now()->getValue());
        $unpublish = strtotime($this->owner->UnPublishOnDate);

        return $now < $unpublish;
    }

    /**
     * Add edit check for when publishing has been scheduled and if any workflow definitions want the item to be disabled.
     */
    public function canEdit($member) {
        if (!Permission::check('EDIT_EMBARGOED_WORKFLOW') && // not given global/override permission to edit
            !$this->owner->AllowEmbargoedEditing) { // item flagged as not editable
            $now = strtotime(DBDatetime::now()->getValue());
            $publishTime = strtotime($this->owner->PublishOnDate);

            if ($publishTime && $publishTime > $now || // when scheduled publish date is in the future
                // when there isn't a publish date, but a Job is in place (publish immediately, but queued jobs is waiting)
                (!$publishTime && $this->owner->PublishJobID != 0)
            ) {
                return false;
            }
        }
    }

    /**
     * Get any future time set in GET param. Must use ISO-8601 format for time to be parsed correctly.
     * e.g: 20160513T2359Z
     *
     * @param  $ctrl  Optional for supplying a controller, useful for unit testing
     * @return string Time in format useful for SQL comparison.
     */
    public function getFutureTime($ctrl = null)
    {
        // Lazy load future time unless we are passing in a controller object explicitly
        if (!static::$future_time || $ctrl) {

            $curr = ($ctrl) ? $ctrl : (Controller::has_curr() ? Controller::curr() : false);

            if ($curr) {
                $ft = $curr->getRequest()->getVar('ft');
                if ($ft) {
                    // Force timezone to UTC so that it does not apply current timezone offset
                    $dt = DateTime::createFromFormat('Ymd\THi\Z', $ft, new DateTimeZone('UTC'));
                    static::$future_time = $dt->format('Y-m-d H:i');
                }
            }
            $time = static::$future_time;

            $this->owner->extend('getWorkflowFutureTime', $time, $curr);

            static::$future_time = $time;
        }

        return static::$future_time;
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
        if ($parsed && $this->owner->has_extension('SilverStripe\\ORM\\Versioning\\Versioned')) {
            return Controller::join_links(
                $this->owner->PreviewLink(),
                '?stage=Stage',
                '?ft=' . date('Ymd\THi\Z', $parsed)
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
        if ($time && $this->owner->has_extension('SilverStripe\\ORM\\Versioning\\Versioned')) {
            $dataQuery->setQueryParam('Future.time', $time);
        }
    }

    /**
     * Alter SQL queries for this object so that the version matching the time that is passed is returned.
     * Relies on Versioned extension as it queries the _versions table and is only triggered when viewing the staging
     * site e.g: ?stage=Stage. This has the side effect that Versioned::canViewVersioned() is used to restrict
     * access.
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null) {
        $time = $dataQuery->getQueryParam('Future.time');

        if (!$time || !$this->owner->has_extension('SilverStripe\\ORM\\Versioning\\Versioned')) {
            return;
        }

        // Only trigger future state when viewing "Stage", this ensures the query works with Versioned::augmentSQL()
        $stage = $dataQuery->getQueryParam('Versioned.stage');
        if ($stage === Versioned::DRAFT) {
            $baseTable = ClassInfo::baseDataClass($dataQuery->dataClass());

            foreach ($query->getFrom() as $alias => $join) {

                if (!class_exists($alias) || !is_a($alias, $baseTable, true)) {
                    continue;
                }

                if ($alias != $baseTable) {
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
            foreach (Config::inst()->get('SilverStripe\\ORM\\Versioning\\Versioned', 'db_for_versions_table') as $name => $type) {
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

                        /* The Draft copy, it is the source of truth for the most part when referencing embargo and expiry dates */
                        LEFT JOIN \"{$baseTable}\" AS Base ON \"Base\".\"ID\" = \"{$baseTable}_versions\".\"RecordID\"

                        /* The Live copy, only used as source of truth if the Draft copy cannot be */
                        LEFT JOIN \"{$baseTable}_Live\" AS Live ON \"Live\".\"ID\" = \"{$baseTable}_versions\".\"RecordID\"

                        WHERE
                            /* Get the latest Draft version */
                            (
                                \"{$baseTable}_versions\".\"WasPublished\" = 0
                                AND
                                /* Within the embargo and expiry range */
                                (\"Base\".\"PublishOnDate\" <= ? OR \"Base\".\"PublishOnDate\" IS NULL)
                                AND
                                (\"Base\".\"UnPublishOnDate\" > ? OR \"Base\".\"UnPublishOnDate\" IS NULL)
                                AND
                                /* Approved, which is marked by a PublishJobID */
                                (\"Base\".\"PublishJobID\" != 0)
                                AND
                                /* Draft exists */
                                \"Base\".\"ID\" IS NOT NULL
                            )
                            OR
                            /* Get the latest Published version */
                            (
                                \"{$baseTable}_versions\".\"WasPublished\" = 1
                                AND
                                (
                                    /* Draft exists, check Draft's unpublish date */
                                    (
                                        \"Base\".\"ID\" IS NOT NULL
                                        AND
                                        (\"Base\".\"UnPublishOnDate\" > ? OR \"Base\".\"UnPublishOnDate\" IS NULL)
                                    )
                                    OR
                                    /* Draft doesn't exist, check Live's unpublish date */
                                    (
                                        \"Base\".\"ID\" IS NULL
                                        AND
                                        (\"Live\".\"UnPublishOnDate\" > ? OR \"Live\".\"UnPublishOnDate\" IS NULL)
                                    )
                                )
                                AND
                                /* Live exists */
                                \"Live\".\"ID\" IS NOT NULL
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

    /**
     * Checks if the current page is in the Pending workflow status
     *
     * @return boolean
     */
    public function checkIsEmbargoExpiryPending() {
        return Versioned::get_stage() === Versioned::DRAFT &&
               ($this->checkValidEmbargoExpiryDate($this->owner->DesiredPublishDate) || $this->checkValidEmbargoExpiryDate($this->owner->DesiredUnPublishDate));
    }

    /**
     * Get the page's workflow status(es), which could be:
     *
     * - Pending:  the changes are saved as draft but not pushed into a workflow
     * - Paused:   the page has started a workflow and paused during the workflow
     * - Complete: the workflow is approved and completed
     *
     * @return array
     */
    public function getEmbargoExpiryStatuses() {
        $statuses = array();
        $instance = null;

        $this->setIsWorkflowInEffect();

        if ($this->getIsWorkflowInEffect()) {
            $instance = $this->workflowService->getWorkflowFor($this->owner, true);
        }

        if ($this->checkValidEmbargoExpiryDate($this->owner->PublishOnDate) || $this->checkValidEmbargoExpiryDate($this->owner->UnPublishOnDate)) {
            array_push($statuses, 'Complete');
        }

        if ($instance && $instance->exists()) {
            if ($instance->WorkflowStatus === 'Paused' || $instance->WorkflowStatus === 'Active') {
                array_push($statuses, 'Paused');
            } else {
                if ($this->checkIsEmbargoExpiryPending() && $instance->WorkflowStatus === 'Complete') {
                    array_push($statuses, 'Pending');
                }
            }
        } elseif ($this->checkIsEmbargoExpiryPending()) {
            array_push($statuses, 'Pending');
        }

        return $statuses;
    }

    /**
     * Checks an embargo/expiry date's validity:
     * - true:  date is in the future
     * - false: the date has already passed
     * - null:  no date was specified (the CMS date input field was left blank)
     *
     * @param string $date DesiredPublishDate|DesiredUnPublishDate|PublishOnDate|UnPublishOnDate
     * @return true|false|null
     */
    public function checkValidEmbargoExpiryDate($date)
    {
        $valid = null;
        $d = DBDatetime::create();
        $d->setValue($date);
        $parsed = strtotime($d->getValue());

        if ($parsed) {
            $now = strtotime(DBDatetime::now()->getValue());
            $valid = $parsed > $now;
        }

        return $valid;
    }

    /**
     * Get formatted embargo/expiry date as a string.
     * If the $date is invalid and a $type embargo|expiry is specified
     * then it returns a respective fallback string.
     *
     * @param string $date DesiredPublishDate|DesiredUnPublishDate|PublishOnDate|UnPublishOnDate
     * @param string $type embargo|expiry
     * @return string
     */
    private function getEmbargoExpiryDate($date, $type)
    {
        $result = '';
        $valid = $this->checkValidEmbargoExpiryDate($date);

        // $date is in the future
        if ($valid) {
            $d = DBDatetime::create();
            $d->setValue($date);
            $result = $d->FormatFromSettings();
        }
        // $date is not in the future
        elseif ($valid === false) {
            if ($type === 'embargo') {
                $result = _t('WorkflowMessage.EMBARGO_DATE_INVALID', 'Immediately');
            } elseif ($type === 'expiry') {
                $result = _t('WorkflowMessage.EXPIRY_DATE_INVALID', 'Never');
            }
        }
        // the datetime fields were blank and saved
        elseif (is_null($valid)) {
            if ($type === 'embargo') {
                $result = _t('WorkflowMessage.EMBARGO_DATE_NULL', 'Immediately');
            } elseif ($type === 'expiry') {
                $result = _t('WorkflowMessage.EXPIRY_DATE_NULL', 'Never');
            }
        }

        return $result;
    }

    /**
     * Get the embargo/expiry author's full name
     *
     * @param string $status Pending|Paused|Complete
     * @return string
     */
    private function getEmbargoExpiryAuthor($status)
    {
        $author = '';
        $authorID = null;

        if ($status === 'Pending') {
            $authorID = Versioned::get_latest_version($this->owner->ClassName, $this->owner->ID)->AuthorID;
        } else {
            $instance = null;

            if ($this->getIsWorkflowInEffect()) {
                $instance = $this->workflowService->getWorkflowFor($this->owner, true);
            }

            if ($instance && $instance->exists() && $status === 'Paused' || $status === 'Complete') {
                $authorID = $instance->InitiatorID;
            }
        }

        if (!is_null($authorID)) {
            $author = Member::get()->byID($authorID)->getName();
        }

        return $author;
    }

    /**
     * Get the name of the Member who's approved this page.
     * Only use for workflow status Complete
     *
     * @return string
     */
    private function getEmbargoExpiryApprover()
    {
        $approver = null;
        $instance = null;

        if ($this->getIsWorkflowInEffect()) {
            $instance = $this->workflowService->getWorkflowFor($this->owner, true);
        }

        if ($instance && $instance->exists()) {
            $approverID = WorkflowActionInstance::get()
                ->filter(array(
                    'Finished' => 1,
                    'WorkflowID' => $instance->ID))
                ->last()
                ->MemberID;
            $approver = is_int($approverID) ? Member::get()->byID($approverID)->getName() : null;
        }

        return $approver;
    }

    /**
     * Render a valid embargo/expiry date with its future time link
     *
     * @param string $date DesiredPublishDate|DesiredUnPublishDate|PublishOnDate|UnPublishOnDate
     * @param string $type embargo|expiry
     * @return string
     */
    private function renderEmbargoExpiryDateLink($date, $type)
    {
        $result = $this->getEmbargoExpiryDate($date, $type);
        if ($this->checkValidEmbargoExpiryDate($date)) {
            $result = LiteralField::create("$type-link", '<a href="' . $this->getFutureTimeLink($date) . '" target="_blank">' . $result . '</a>');
        }
        return $result;
    }

    /**
     * Generate values for each workflow status message box, which includes:
     *
     * - Style          The message's CSS class warning|info
     * - Title          Title stating the workflow's state for Pending|Paused|Complete
     * - Author         The author's full name
     * - DatePublish    Formatted desired|scheduled publish date & time
     * - DateUnPublish  Formatted desired|scheduled expiry date & time
     *
     * @param string $status Pending|Paused|Complete
     * @return array
     */
    private function getEmbargoExpiryMessage($status)
    {
        $message = array();

        $prefixRequested = _t('WorkflowMessage.DATE_PREFIX_REQUESTED', 'Requested');
        $prefixScheduled = _t('WorkflowMessage.DATE_PREFIX_REQUESTED', 'Scheduled');

        $message['Author'] = $this->getEmbargoExpiryAuthor($status);

        switch ($status) {
            case 'Pending':
                $message['Style'] = 'warning';
                $message['Title'] = _t('WorkflowMessage.TITLE_PENDING', 'Embargo/expiry saved in draft');
                $message['DatePrefix'] = $prefixRequested;
                $message['DatePublish'] = $this->getEmbargoExpiryDate($this->owner->DesiredPublishDate, 'embargo');
                $message['DateUnPublish'] = $this->getEmbargoExpiryDate($this->owner->DesiredUnPublishDate, 'expiry');
                break;
            case 'Paused':
                $message['Style'] = 'warning';
                $message['Title'] = _t('WorkflowMessage.TITLE_PAUSED', 'Awaiting approval');
                $message['DatePrefix'] = $prefixRequested;
                $message['DatePublish'] = $this->getEmbargoExpiryDate($this->owner->DesiredPublishDate, 'embargo');
                $message['DateUnPublish'] = $this->getEmbargoExpiryDate($this->owner->DesiredUnPublishDate, 'expiry');
                break;
            case 'Complete':
                $message['Style'] = 'notice';
                $message['Title'] = _t('WorkflowMessage.TITLE_COMPLETE', 'Approved changes');
                $message['Approver'] = $this->getEmbargoExpiryApprover();
                $message['DatePrefix'] = $prefixScheduled;
                $message['DatePublish'] = $this->renderEmbargoExpiryDateLink($this->owner->PublishOnDate, 'embargo');
                $message['DateUnPublish'] = $this->renderEmbargoExpiryDateLink($this->owner->UnPublishOnDate, 'expiry');
        }

        return $message;
    }

    /**
     * Renders the embargo expiry message(s) to view
     *
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    private function renderEmbargoExpiryMessages()
    {
        $messages = ArrayList::create();
        $statuses = $this->getEmbargoExpiryStatuses();

        foreach ($statuses as $status) {
            $messages->add($this->getEmbargoExpiryMessage($status));
        }

        $view = $this->owner->customise(
            array(
                'WorkflowMessages' => $messages
            )
        )->renderWith('Includes/WorkflowStatusMessage');

        return $view;
    }
}
