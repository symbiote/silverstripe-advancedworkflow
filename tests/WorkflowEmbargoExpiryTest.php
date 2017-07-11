<?php

use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\ORM\Versioning\Versioned;
use SilverStripe\Dev\SapphireTest;

/**
 * @author marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class WorkflowEmbargoExpiryTest extends SapphireTest {

    public static $fixture_file = 'advancedworkflow/tests/WorkflowEmbargoExpiry.yml';

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

	public function __construct()
	{
		if (!class_exists('AbstractQueuedJob')) {
			$this->skipTest = true;
		}
		parent::__construct();
	}

	/**
	 * Start a workflow for a page,
	 * this will set it into a state where a workflow is currently being processes
	 *
	 * @param DataObject $obj
	 * @return DataObject
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
	 * @param DataObject $obj
	 * @return DataObject
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

    /**
     * Retrieves the live version for an object
     *
     * @param DataObject $obj
     * @return DataObject
     */
    private function getLive($obj)
    {
        $oldMode = Versioned::get_reading_mode();
        Versioned::set_reading_mode(Versioned::LIVE);
        $live = DataObject::get_by_id($obj->ClassName, $obj->ID);
        Versioned::set_reading_mode($oldMode);

        return $live;
    }

    /**
     * Test when embargo and expiry are both empty.
     *
     * No jobs should be created, but page is published by the workflow action.
     */
    public function testEmptyEmbargoExpiry()
    {
        $page = $this->objFromFixture('SiteTree', 'emptyEmbargoExpiry');
        $page->Content = 'Content to go live';

        $live = $this->getLive($page);

        $this->assertEmpty($live->Content);
        $this->assertEquals(0, $page->PublishJobID);
        $this->assertEquals(0, $page->UnPublishJobID);

        $page = $this->finishWorkflow($page);

        $live = $this->getLive($page);

        $this->assertNotEmpty($live->Content);
        $this->assertEquals(0, $page->PublishJobID);
        $this->assertEquals(0, $page->UnPublishJobID);
    }

    /**
     * Test for embargo in the past
     *
     * Creates a publish job which is queued for immediately
     */
    public function testPastEmbargo()
    {
        $page = $this->objFromFixture('SiteTree', 'pastEmbargo');

        $page = $this->finishWorkflow($page);

        $this->assertNotEquals(0, $page->PublishJobID);
        $this->assertEquals(0, $page->UnPublishJobID);

        $publish = strtotime($page->PublishJob()->StartAfter);

        $this->assertFalse($publish);
    }

    /**
     * Test for expiry in the past
     *
     * Creates an unpublish job which is queued for immediately
     */
    public function testPastExpiry()
    {
        $page = $this->objFromFixture('SiteTree', 'pastExpiry');

        $page = $this->finishWorkflow($page);

        $this->assertEquals(0, $page->PublishJobID);
        $this->assertNotEquals(0, $page->UnPublishJobID);

        $unpublish = strtotime($page->UnPublishJob()->StartAfter);

        $this->assertFalse($unpublish);
    }

    /**
     * Test for embargo and expiry in the past
     *
     * Creates an unpublish job which is queued for immediately
     */
    public function testPastEmbargoExpiry()
    {
        $page = $this->objFromFixture('SiteTree', 'pastEmbargoExpiry');

        $page = $this->finishWorkflow($page);

        $this->assertEquals(0, $page->PublishJobID);
        $this->assertNotEquals(0, $page->UnPublishJobID);

        $unpublish = strtotime($page->UnPublishJob()->StartAfter);

        $this->assertFalse($unpublish);
    }

    /**
     * Test for embargo in the past and expiry in the future
     *
     * Creates a publish job which is queued for immediately and an unpublish job which is queued for later
     */
    public function testPastEmbargoFutureExpiry()
    {
        $page = $this->objFromFixture('SiteTree', 'pastEmbargoFutureExpiry');

        $page = $this->finishWorkflow($page);

        $this->assertNotEquals(0, $page->PublishJobID);
        $this->assertNotEquals(0, $page->UnPublishJobID);

        $publish = strtotime($page->PublishJob()->StartAfter);
        $unpublish = strtotime($page->UnPublishJob()->StartAfter);

        $this->assertFalse($publish);
        $this->assertNotFalse($unpublish);
    }

    /**
     * Test for embargo and expiry in the future
     *
     * Creates a publish and unpublish job which are queued for immediately
     */
    public function testFutureEmbargoExpiry()
    {
        $page = $this->objFromFixture('SiteTree', 'futureEmbargoExpiry');

        $page = $this->finishWorkflow($page);

        $this->assertNotEquals(0, $page->PublishJobID);
        $this->assertNotEquals(0, $page->UnPublishJobID);

        $publish = strtotime($page->PublishJob()->StartAfter);
        $unpublish = strtotime($page->UnPublishJob()->StartAfter);

        $this->assertNotFalse($publish);
        $this->assertNotFalse($unpublish);
    }

    /**
     * Test for embargo after expiry in the past
     *
     * No jobs should be created, invalid option
     */
    public function testPastEmbargoAfterExpiry()
    {
        $page = $this->objFromFixture('SiteTree', 'pastEmbargoAfterExpiry');

        $page = $this->finishWorkflow($page);

        $this->assertEquals(0, $page->PublishJobID);
        $this->assertEquals(0, $page->UnPublishJobID);
    }

    /**
     * Test for embargo after expiry in the future
     *
     * No jobs should be created, invalid option
     */
    public function testFutureEmbargoAfterExpiry()
    {
        $page = $this->objFromFixture('SiteTree', 'futureEmbargoAfterExpiry');

        $page = $this->finishWorkflow($page);

        $this->assertEquals(0, $page->PublishJobID);
        $this->assertEquals(0, $page->UnPublishJobID);
    }

    /**
     * Test for embargo and expiry in the past, both have the same value
     *
     * No jobs should be created, invalid option
     */
    public function testPastSameEmbargoExpiry()
    {
        $page = $this->objFromFixture('SiteTree', 'pastSameEmbargoExpiry');

        $page = $this->finishWorkflow($page);

        $this->assertEquals(0, $page->PublishJobID);
        $this->assertEquals(0, $page->UnPublishJobID);
    }

    /**
     * Test for embargo and expiry in the future, both have the same value
     *
     * No jobs should be created, invalid option
     */
    public function testFutureSameEmbargoExpiry()
    {
        $page = $this->objFromFixture('SiteTree', 'futureSameEmbargoExpiry');

        $page = $this->finishWorkflow($page);

        $this->assertEquals(0, $page->PublishJobID);
        $this->assertEquals(0, $page->UnPublishJobID);
    }

    /**
     * When an item is queued for publishing or unpublishing and new dates are entered
     *
     * The existing queued jobs should be cleared
     */
	public function testDesiredRemovesJobs()
    {
        $page = $this->objFromFixture('SiteTree', 'futureEmbargoExpiry');

        $page = $this->finishWorkflow($page);

		$this->assertNotEquals(0, $page->PublishJobID);
		$this->assertNotEquals(0, $page->UnPublishJobID);

		$page->DesiredPublishDate = '2020-02-01 00:00:00';
		$page->DesiredUnPublishDate = '2020-02-01 02:00:00';

		$page->write();

		$this->assertEquals(0, $page->PublishJobID);
		$this->assertEquals(0, $page->UnPublishJobID);
	}

    /**
     * Tests that checking for publishing scheduled state is working
     */
    public function testIsPublishScheduled()
    {
        $page = SiteTree::create();
        $page->Title = 'My page';
        $page->PublishOnDate = '2010-01-01 00:00:00';
        $page->AllowEmbargoedEditing = false;
        $page->write();

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
        $page->Title = 'My page';
        $page->PublishOnDate = '2010-01-01 00:00:00';
        $page->AllowEmbargoedEditing = false;
        $page->write();

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

    /**
     * Make sure that publish and unpublish dates are not carried over to the duplicates.
     */
    public function testDuplicateRemoveEmbargoExpiry() {
        $page = $this->objFromFixture('SiteTree', 'futureEmbargoExpiry');

        // fake publish jobs
        $page = $this->finishWorkflow($page);

        $dupe = $page->duplicate();
        $this->assertNotNull($page->PublishOnDate, 'Not blank publish on date');
        $this->assertNotNull($page->UnPublishOnDate, 'Not blank unpublish on date');
        $this->assertNotEquals($page->PublishJobID, 0, 'Publish job ID still set');
        $this->assertNotEquals($page->UnPublishJobID, 0, 'Unpublish job ID still set');
        $this->assertNull($dupe->PublishOnDate, 'Blank publish on date');
        $this->assertNull($dupe->UnPublishOnDate, 'Blank unpublish on date');
        $this->assertEquals($dupe->PublishJobID, 0, 'Publish job ID unset');
        $this->assertEquals($dupe->UnPublishJobID, 0, 'Unpublish job ID unset');
    }

    protected function logOut()
    {
        if($member = Member::currentUser()) $member->logOut();
    }


}
