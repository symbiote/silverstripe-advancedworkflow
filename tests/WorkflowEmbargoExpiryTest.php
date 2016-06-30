<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class WorkflowEmbargoExpiryTest extends SapphireTest
{

    public function setUp()
    {
        parent::setUp();

        SS_Datetime::set_mock_now('2014-01-05 12:00:00');


        // Prevent failure if queuedjobs module isn't installed.
        if (!class_exists('AbstractQueuedJob', false)) {
            $this->markTestSkipped("This test requires queuedjobs");
        }
    }

    public function tearDown()
    {
        SS_Datetime::clear_mock_now();
        parent::tearDown();
    }

    /**
     * @var array
     */
    protected $requiredExtensions = array(
        'SiteTree' => array(
            'WorkflowEmbargoExpiryExtension',
            'Versioned',
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
        $now = strtotime(SS_Datetime::now()->getValue());
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
        $now = strtotime(SS_Datetime::now()->getValue());
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
        $now = strtotime(SS_Datetime::now()->getValue());
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
        $this->assertGreaterThan(strtotime(SS_Datetime::now()->getValue()), $unpublish); // for later run
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
        $this->assertGreaterThan(strtotime(SS_Datetime::now()->getValue()), $publish); // for later run
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
        SS_Datetime::set_mock_now('2016-01-16 00:00:00');
        $this->assertTrue($page->getIsPublishScheduled());

        SS_Datetime::set_mock_now('2016-02-16 00:00:00');
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
        SS_Datetime::set_mock_now('2016-01-16 00:00:00');
        $this->assertTrue($page->getIsUnPublishScheduled());

        SS_Datetime::set_mock_now('2016-02-16 00:00:00');
        $this->assertFalse($page->getIsUnPublishScheduled());
    }

    /**
     * Tests that status flags (badges) are added properly for a page
     */
    public function testStatusFlags()
    {
        $page = SiteTree::create();
        $page->Title = 'stuff';
        SS_Datetime::set_mock_now('2016-01-16 00:00:00');

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
