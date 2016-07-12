<?php

use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Versioning\Versioned;

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class WorkflowEmbargoExpiryTest extends SapphireTest
{
    protected static $fixture_file = 'workflowembargoexpiry.yml';

    public function setUp()
    {
        parent::setUp();

        DBDatetime::set_mock_now('2014-01-05 12:00:00');


        // Prevent failure if queuedjobs module isn't installed.
        if (!class_exists('AbstractQueuedJob', false)) {
            $this->markTestSkipped("This test requires queuedjobs");
        }
    }

    public function tearDown()
    {
        DBDatetime::clear_mock_now();
        parent::tearDown();
    }

    /**
     * @var array
     */
    protected $requiredExtensions = array(
        'SiteTree' => array(
            'WorkflowEmbargoExpiryExtension',
            'SilverStripe\\ORM\\Versioning\\Versioned',
        )
    );

    /**
     * @var array
     */
    protected $illegalExtensions = array(
        'SiteTree' => array(
            "Translatable",
        )
    );

    /**
     * Start a workflow for a page,
     * this will set it into a state where a workflow is currently being processes
     *
     * @param SiteTree $obj
     * @return SiteTree
     */
    private function startWorkflow($obj)
    {
        $workflow = $this->objFromFixture('WorkflowDefinition', 'requestPublication');
        $obj->WorkflowDefinitionID = $workflow->ID;
        $obj->write();

        $svc = singleton('WorkflowService');
        $svc->startWorkflow($obj, $obj->WorkflowDefinitionID);
        return $obj;
    }

    /**
     * Start and finish a workflow which will publish the page immediately basically.
     *
     * @param SiteTree $obj
     * @return SiteTree
     */
    private function finishWorkflow($obj)
    {
        $workflow = $this->objFromFixture('WorkflowDefinition', 'approvePublication');
        $obj->WorkflowDefinitionID = $workflow->ID;
        $obj->write();

        $svc = singleton('WorkflowService');
        $svc->startWorkflow($obj, $obj->WorkflowDefinitionID);

        $obj = DataObject::get_by_id($obj->ClassName, $obj->ID);
        return $obj;
    }

    public function __construct()
    {
        if (!class_exists('AbstractQueuedJob')) {
            $this->skipTest = true;
        }
        parent::__construct();
    }

    public function testFutureDatesJobs()
    {
        $page = new SiteTree();

        $page->PublishOnDate = '2020-01-01 00:00:00';
        $page->UnPublishOnDate = '2020-01-01 01:00:00';

        // Two writes are necessary for this to work on new objects
        $page->write();
        $page->write();

        $this->assertTrue($page->PublishJobID > 0);
        $this->assertTrue($page->UnPublishJobID > 0);

        // Check date ranges
        $now = strtotime(DBDatetime::now()->getValue());
        $publish = strtotime($page->PublishJob()->StartAfter);
        $unPublish = strtotime($page->UnPublishJob()->StartAfter);

        $this->assertGreaterThan($now, $publish);
        $this->assertGreaterThan($now, $unPublish);
        $this->assertGreaterThan($publish, $unPublish);
    }

    public function testDesiredRemovesJobs()
    {
        $page = new SiteTree();

        $page->PublishOnDate = '2020-01-01 00:00:00';
        $page->UnPublishOnDate = '2020-01-01 01:00:00';

        // Two writes are necessary for this to work on new objects
        $page->write();
        $page->write();

        $this->assertTrue($page->PublishJobID > 0);
        $this->assertTrue($page->UnPublishJobID > 0);

        // Check date ranges
        $now = strtotime(DBDatetime::now()->getValue());
        $publish = strtotime($page->PublishJob()->StartAfter);
        $unPublish = strtotime($page->UnPublishJob()->StartAfter);

        $this->assertGreaterThan($now, $publish);
        $this->assertGreaterThan($now, $unPublish);
        $this->assertGreaterThan($publish, $unPublish);

        $page->DesiredPublishDate = '2020-02-01 00:00:00';
        $page->DesiredUnPublishDate = '2020-02-01 02:00:00';

        $page->write();

        $this->assertTrue($page->PublishJobID == 0);
        $this->assertTrue($page->UnPublishJobID == 0);
    }

    public function testPublishActionWithFutureDates()
    {
        $action = new PublishItemWorkflowAction();
        $instance = new WorkflowInstance();

        $page = new SiteTree();
        $page->Title = 'stuff';
        $page->DesiredPublishDate = '2020-02-01 00:00:00';
        $page->DesiredUnPublishDate = '2020-02-01 02:00:00';

        $page->write();

        $instance->TargetClass = $page->ClassName;
        $instance->TargetID = $page->ID;

        $action->execute($instance);

        $page = DataObject::get_by_id('SiteTree', $page->ID);
        $this->assertTrue($page->PublishJobID > 0);
        $this->assertTrue($page->UnPublishJobID > 0);

        // Check date ranges
        $now = strtotime(DBDatetime::now()->getValue());
        $publish = strtotime($page->PublishJob()->StartAfter);
        $unPublish = strtotime($page->UnPublishJob()->StartAfter);

        $this->assertGreaterThan($now, $publish);
        $this->assertGreaterThan($now, $unPublish);
        $this->assertGreaterThan($publish, $unPublish);
    }

    /**
     * Test that a page with a past publish date creates the correct jobs
     */
    public function testPastPublishThenUnpublish()
    {
        $page = new SiteTree();
        $page->Title = 'My Page';
        $page->write();

        // No publish
        $this->assertEmpty($page->PublishJobID);
        $this->assertEmpty($page->UnPublishJobID);

        // Set a past publish date
        $page->PublishOnDate = '2010-01-01 00:00:00';
        $page->write();

        // We should still have a job to publish this page, but not unpublish
        $this->assertNotEmpty($page->PublishJobID);
        $this->assertEmpty($page->UnPublishJobID);

        // Check that this job is set for immediate run
        $publish = strtotime($page->PublishJob()->StartAfter);
        $this->assertEmpty($publish);

        // Now add an expiry date in the past, but after the current publish job,
        // and ensure that this correctly overrides the open publish request
        $page->UnPublishOnDate = '2010-01-02 00:00:00';
        $page->write();

        // Now we should have an unpublish job, but the publish job is noticably absent
        $this->assertEmpty($page->PublishJobID);
        $this->assertNotEmpty($page->UnPublishJobID);

        // Check that this unpublish job is set for immediate run
        $unpublish = strtotime($page->UnPublishJob()->StartAfter);
        $this->assertEmpty($unpublish);

        // Now add an expiry date in the future, and ensure that we get the correct combination of
        // publish and unpublish jobs
        $page->UnPublishOnDate = '2015-01-01 12:00:00';
        $page->write();

        // Both jobs exist
        $this->assertNotEmpty($page->PublishJobID);
        $this->assertNotEmpty($page->UnPublishJobID);

        // Check that this unpublish job is set for immediate run and the unpublish for future
        $publish = strtotime($page->PublishJob()->StartAfter);
        $unpublish = strtotime($page->UnPublishJob()->StartAfter);
        $this->assertEmpty($publish); // for immediate run
        $this->assertGreaterThan(strtotime(DBDatetime::now()->getValue()), $unpublish); // for later run
    }

    /**
     * Test that a page with a past unpublish date creates the correct jobs
     */
    public function testPastUnPublishThenPublish()
    {
        $page = new SiteTree();
        $page->Title = 'My Page';
        $page->write();

        // No publish
        $this->assertEmpty($page->PublishJobID);
        $this->assertEmpty($page->UnPublishJobID);

        // Set a past unpublish date
        $page->UnPublishOnDate = '2010-01-01 00:00:00';
        $page->write();

        // We should still have a job to unpublish this page, but not publish
        $this->assertEmpty($page->PublishJobID);
        $this->assertNotEmpty($page->UnPublishJobID);

        // Check that this job is set for immediate run
        $unpublish = strtotime($page->UnPublishJob()->StartAfter);
        $this->assertEmpty($unpublish);

        // Now add an publish date in the past, but after the unpublish job,
        // and ensure that this correctly overrides the open unpublish request
        $page->PublishOnDate = '2010-01-02 00:00:00';
        $page->write();

        // Now we should have an publish job, but the unpublish job is noticably absent
        $this->assertNotEmpty($page->PublishJobID);
        $this->assertEmpty($page->UnPublishJobID);

        // Check that this publish job is set for immediate run
        $publish = strtotime($page->PublishJob()->StartAfter);
        $this->assertEmpty($publish);

        // Now add a publish date in the future, and ensure that we get the correct combination of
        // publish and unpublish jobs
        $page->PublishOnDate = '2015-01-01 12:00:00';
        $page->write();

        // Both jobs exist
        $this->assertNotEmpty($page->PublishJobID);
        $this->assertNotEmpty($page->UnPublishJobID);

        // Check that this unpublish job is set for immediate run and the unpublish for future
        $publish = strtotime($page->PublishJob()->StartAfter);
        $unpublish = strtotime($page->UnPublishJob()->StartAfter);
        $this->assertEmpty($unpublish); // for immediate run
        $this->assertGreaterThan(strtotime(DBDatetime::now()->getValue()), $publish); // for later run
    }

    public function testPastPublishWithWorkflowInEffect()
    {
        $definition = $this->createDefinition();

        $page = new SiteTree();
        $page->Title = 'My page';
        $page->WorkflowDefinitionID = $definition->ID;
        $page->write();

        // No publish
        $this->assertEmpty($page->PublishJobID);
        $this->assertEmpty($page->UnPublishJobID);

        // Set a past publish date
        $page->DesiredPublishDate = '2010-01-01 00:00:00';
        $page->write();

        // Workflow is in effect. No jobs have been created yet as it's not approved.
        $this->assertEmpty($page->PublishJobID);
        $this->assertEmpty($page->UnPublishJobID);

        // Advance the workflow so we can see what happens
        $instance = new WorkflowInstance();
        $instance->beginWorkflow($definition, $page);
        $instance->execute();

        // execute the "publish" workflow action
        $action = new PublishItemWorkflowAction();
        $action->execute($instance);

        // re-fetch the Page again.
        $page = SiteTree::get()->byId($page->ID);

        // We now have a PublishOnDate field set
        $this->assertEquals('2010-01-01 00:00:00', $page->PublishOnDate);
        $this->assertEmpty($page->DesiredPublishDate);

        // Publish job has been setup
        $this->assertNotEmpty($page->PublishJobID);
        $this->assertEmpty($page->UnPublishJobID);

        // Check that this publish job is set for immediate run
        $publish = strtotime($page->PublishJob()->StartAfter);
        $this->assertEmpty($publish);
    }

    /**
     * Tests that checking for publishing scheduled state is working
     */
    public function testIsPublishScheduled()
    {
        $page = SiteTree::create();
        $page->Title = 'stuff';

        $this->assertFalse($page->getIsPublishScheduled());

        $page->PublishOnDate = '2016-02-01 00:00:00';
        DBDatetime::set_mock_now('2016-01-16 00:00:00');
        $this->assertTrue($page->getIsPublishScheduled());

        DBDatetime::set_mock_now('2016-02-16 00:00:00');
        $this->assertFalse($page->getIsPublishScheduled());
    }

    /**
     * Tests that checking for un-publishing scheduled state is working
     */
    public function testIsUnPublishScheduled()
    {
        $page = SiteTree::create();
        $page->Title = 'stuff';

        $this->assertFalse($page->getIsUnPublishScheduled());

        $page->UnPublishOnDate = '2016-02-01 00:00:00';
        DBDatetime::set_mock_now('2016-01-16 00:00:00');
        $this->assertTrue($page->getIsUnPublishScheduled());

        DBDatetime::set_mock_now('2016-02-16 00:00:00');
        $this->assertFalse($page->getIsUnPublishScheduled());
    }

    /**
     * Tests that status flags (badges) are added properly for a page
     */
    public function testStatusFlags()
    {
        $page = SiteTree::create();
        $page->Title = 'stuff';
        DBDatetime::set_mock_now('2016-01-16 00:00:00');

        $flags = $page->getStatusFlags(false);
        $this->assertNotContains('embargo_expiry', array_keys($flags));
        $this->assertNotContains('embargo', array_keys($flags));
        $this->assertNotContains('expiry', array_keys($flags));

        $page->PublishOnDate = '2016-02-01 00:00:00';
        $page->UnPublishOnDate = null;
        $flags = $page->getStatusFlags(false);
        $this->assertNotContains('embargo_expiry', array_keys($flags));
        $this->assertContains('embargo', array_keys($flags));
        $this->assertNotContains('expiry', array_keys($flags));

        $page->PublishOnDate = null;
        $page->UnPublishOnDate = '2016-02-01 00:00:00';
        $flags = $page->getStatusFlags(false);
        $this->assertNotContains('embargo_expiry', array_keys($flags));
        $this->assertNotContains('embargo', array_keys($flags));
        $this->assertContains('expiry', array_keys($flags));

        $page->PublishOnDate = '2016-02-01 00:00:00';
        $page->UnPublishOnDate = '2016-02-08 00:00:00';
        $flags = $page->getStatusFlags(false);
        $this->assertContains('embargo_expiry', array_keys($flags));
        $this->assertNotContains('embargo', array_keys($flags));
        $this->assertNotContains('expiry', array_keys($flags));
    }

    /**
     * Test workflow definition "Can disable edits during embargo"
     * Make sure page cannot be edited when an embargo is in place
     */
    public function testCanEditConfig()
    {

        $page = SiteTree::create();
        $page->Title = 'My page';
        $page->PublishOnDate = '2010-01-01 00:00:00';
        $page->AllowEmbargoedEditing = false;
        $page->write();

        $memberID = $this->logInWithPermission('SITETREE_EDIT_ALL');
        $this->assertTrue($page->canEdit(), 'Can edit page without embargo and no permission');

        $page->PublishOnDate = '2020-01-01 00:00:00';
        $page->write();
        $this->assertFalse($page->canEdit(), 'Cannot edit page with embargo and no permission');

        $this->logOut();
        $memberID = $this->logInWithPermission('ADMIN');
        $this->assertTrue($page->canEdit(), 'Can edit page with embargo as Admin');

        $this->logOut();
        $memberID = $this->logInWithPermission(array('SITETREE_EDIT_ALL', 'EDIT_EMBARGOED_WORKFLOW'));
        $this->assertTrue($page->canEdit(), 'Can edit page with embargo and permission');

        $page->PublishOnDate = '2010-01-01 00:00:00';
        $page->write();
        $this->assertTrue($page->canEdit(), 'Can edit page without embargo and permission');
    }

    /**
     * Test valid embargo/expiry dates
     */
    public function testCheckValidEmbargoExpiryDate()
    {
        $page = SiteTree::create();
        $now = strtotime(DBDatetime::now());

        $future = $now + 86400;
        $this->assertTrue($page->checkValidEmbargoExpiryDate($future),
            'check that future date is valid');

        $past = $now - 86400;
        $this->assertFalse($page->checkValidEmbargoExpiryDate($past),
            'check that past date is invalid');

        $this->assertFalse($page->checkValidEmbargoExpiryDate($now),
            'check that present date is invalid');

        $blank = null;
        $this->assertNull($page->checkValidEmbargoExpiryDate($blank),
            'check that blank entries return null');

        $wrongType = false;
        $this->assertNull($page->checkValidEmbargoExpiryDate($wrongType),
            'check that incorrect entries return null');

        $wrongString = 'Christmas';
        $this->assertNull($page->checkValidEmbargoExpiryDate($wrongString),
            'check that incorrect date strings return null');
    }

    /**
     * Test we're getting the Pending embargo/expiry status
     */
    public function testGetEmbargoExpiryStatusesPending()
    {
        $page = SiteTree::create();
        $page->set_stage(Versioned::DRAFT);

        $page->DesiredPublishDate = '2014-01-12 00:00:00';
        $page->DesiredUnPublishDate = '2014-01-19 00:00:00';
        $page->write();
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertContains('Pending', $statuses, 'check the page\'s statuses array contains Pending, when both desired embargo & expiry dates are entered');

        $page->DesiredPublishDate = '2014-01-12 00:00:00';
        $page->DesiredUnPublishDate = null;
        $page->write();
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertContains('Pending', $statuses, 'check the page\'s statuses array contains Pending, when only desired embargo date is entered');

        $page->DesiredPublishDate = null;
        $page->DesiredUnPublishDate = '2014-01-19 00:00:00';
        $page->write();
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertContains('Pending', $statuses, 'check the page\'s statuses array contains Pending, when only desired expiry date is entered');

        $page->DesiredPublishDate = null;
        $page->DesiredUnPublishDate = null;
        $page->write();
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertNotContains('Pending', $statuses, 'check the page\'s statuses array does not contain Pending, when no desired embargo & expiry dates are entered');

        $page->DesiredPublishDate = '2014-01-01 00:00:00';
        $page->DesiredUnPublishDate = '2013-01-19 00:00:00';
        $page->write();
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertNotContains('Pending', $statuses, 'check the page\'s statuses array does not contain Pending, when invalid desired embargo & expiry dates are entered');
    }

    /**
     * Test Paused state
     */
    public function testGetEmbargoExpiryStatusesPaused()
    {
        $page = SiteTree::create();
        $page->set_stage(Versioned::DRAFT);

        $page->DesiredPublishDate = '2014-01-12 00:00:00';
        $page->DesiredUnPublishDate = '2014-01-19 00:00:00';
        $page->write();
        $page = $this->startWorkflow($page);
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertContains('Paused', $statuses, 'check the page\'s statuses array contains Paused');
        $this->assertNotContains('Pending', $statuses, 'check the page\'s statuses array excludes Pending');

        $page = SiteTree::create();
        $page->set_stage(Versioned::DRAFT);
        $page = $this->startWorkflow($page);
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertContains('Paused', $statuses, 'check the page\'s statuses array includes Paused');
        $this->assertNotContains('Pending', $statuses, 'check the page\'s statuses array excludes Pending, because there are no embargo expiry dates');

        $page = SiteTree::create();
        $page->set_stage(Versioned::DRAFT);
        $page->DesiredPublishDate = '2013-01-12 00:00:00';
        $page->DesiredUnPublishDate = '2013-01-19 00:00:00';
        $page->write();
        $page = $this->startWorkflow($page);
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertContains('Paused', $statuses, 'check the page\'s statuses array includes Paused');
        $this->assertNotContains('Pending', $statuses, 'check the page\'s statuses array excludes Pending, because the embargo expiry dates are invalid');
    }

    /**
     * Test Complete state
     */
    public function testGetEmbargoExpiryStatusesComplete()
    {
        $page = SiteTree::create();
        $page->DesiredPublishDate = '2014-01-12 00:00:00';
        $page->write();
        $page = $this->finishWorkflow($page);
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertNotContains('Paused', $statuses, 'check the page\'s statuses array excludes Paused, because the page has been approved');
        $this->assertContains('Complete', $statuses, 'check the page\'s statuses array contains Complete');

        $page = SiteTree::create();
        $page->DesiredUnPublishDate = '2014-01-19 00:00:00';
        $page->write();
        $page = $this->finishWorkflow($page);
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertNotContains('Paused', $statuses, 'check the page\'s statuses array excludes Paused, because the page has been approved');
        $this->assertContains('Complete', $statuses, 'check the page\'s statuses array contains Complete');

        $page = SiteTree::create();
        $page->DesiredPublishDate = '2014-01-12 00:00:00';
        $page->DesiredUnPublishDate = '2014-01-19 00:00:00';
        $page->write();
        $page = $this->finishWorkflow($page);
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertNotContains('Paused', $statuses, 'check the page\'s statuses array excludes Paused, because the page has been approved');
        $this->assertContains('Complete', $statuses, 'check the page\'s statuses array contains Complete');

        $page->DesiredPublishDate = '2014-01-19 00:00:00';
        $page->DesiredUnPublishDate = '2014-01-26 00:00:00';
        $page->write();
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertNotContains('Paused', $statuses, 'check the page\'s statuses array excludes Paused, because the page has been approved');
        $this->assertContains('Complete', $statuses, 'check the page\'s statuses array contains Complete');
        $this->assertContains('Pending', $statuses, 'check the page\'s statuses array contains Pending');

        $page = SiteTree::create();
        $page->DesiredPublishDate = '2013-01-12 00:00:00';
        $page->DesiredUnPublishDate = '2013-01-19 00:00:00';
        $page->write();
        $page = $this->finishWorkflow($page);
        $statuses = $page->getEmbargoExpiryStatuses();
        $this->assertNotContains('Paused', $statuses, 'check the page\'s statuses array excludes Paused, because the page has been approved');
        $this->assertNotContains('Complete', $statuses, 'check the page\'s statuses array does not contain Complete, because the desired embargo & expiry dates are invalid');
    }

    protected function createDefinition()
    {
        $definition = new WorkflowDefinition();
        $definition->Title = 'Dummy Workflow Definition';
        $definition->write();

        $stepOne = new WorkflowAction();
        $stepOne->Title = 'Step One';
        $stepOne->WorkflowDefID = $definition->ID;
        $stepOne->write();

        $stepTwo = new WorkflowAction();
        $stepTwo->Title = 'Step Two';
        $stepTwo->WorkflowDefID = $definition->ID;
        $stepTwo->write();

        $transitionOne = new WorkflowTransition();
        $transitionOne->Title = 'Step One T1';
        $transitionOne->ActionID = $stepOne->ID;
        $transitionOne->NextActionID = $stepTwo->ID;
        $transitionOne->write();

        return $definition;
    }

    protected function logOut()
    {
        if($member = Member::currentUser()) $member->logOut();
    }
}

class WorkflowEmbargoExpiryTest_DummyWorkflowAction extends WorkflowAction
{
    public function execute(WorkflowInstance $workflow)
    {
        return false;
    }
}
