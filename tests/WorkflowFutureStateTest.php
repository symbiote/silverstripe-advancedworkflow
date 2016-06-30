<?php


use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Versioning\Versioned;
use SilverStripe\ORM\DataObject;



/**
 * Testing future state in various scenarios.
 */
class WorkflowFutureStateTest extends FunctionalTest
{
    protected static $fixture_file = 'workflowfuturestate.yml';

    protected $requiredExtensions = array(
        'SiteTree' => array(
            'WorkflowEmbargoExpiryExtension',
            'WorkflowApplicable',
            'SilverStripe\\ORM\\Versioning\\Versioned'
        )
    );

    protected $illegalExtensions = array(
        'SiteTree' => array(
            'Translatable',
            'SiteTreeSubsites'
        )
    );

    /**
     * Start a workflow for a page, this will set it into a state where a workflow
     * is currently being processed.
     *
     * @param  SiteTree $obj
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
     * @param  SiteTree $obj
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

    public function setUp()
    {
        parent::setUp();

        // Set current date time to midday on 13th of June 2016
        DBDatetime::set_mock_now('2016-06-13 12:00:00');

        // Prevent failure if queuedjobs module isn't installed.
        if (!class_exists('AbstractQueuedJob')) {
            $this->markTestSkipped("This test requires queuedjobs");
        }
    }

    public function tearDown()
    {
        DBDatetime::clear_mock_now();
        parent::tearDown();
    }

    /**
     * Current date and time is mocked to 2016-06-13 12:00:00
     */
    public function testNowMocked()
    {
        $this->assertEquals('2016-06-13 12:00:00', DBDatetime::now()->getValue());
    }

    /**
     * Draft pages are not returned for future state queries.
     */
    public function testDraftOnly()
    {
        $draft = $this->objFromFixture('SiteTree', 'basic');

        // Check that there is no live version
        $this->assertTrue($draft->isOnDraft());
        $this->assertFalse($draft->isPublished());

        // No live page exists
        $res = $this->get($draft->Link());
        $this->assertEquals(404, $res->getStatusCode());

        // When requesting a page for future time the draft is NOT returned
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => '2016-06-14 00:00:01',
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());
    }

    /**
     * Draft pages that are in a workflow do not show in future state.
     * This essentially tests blank embargo and expiry dates for a page which infer immediate publish
     * and never unpublish.
     */
    public function testDraftInWorkflow()
    {
        $draft = $this->startWorkflow($this->objFromFixture('SiteTree', 'inWorkflow'));

        // Check that there is no live version
        $this->assertTrue($draft->isOnDraft());
        $this->assertFalse($draft->isPublished());

        // No live page exists
        $res = $this->get($draft->Link());
        $this->assertEquals(404, $res->getStatusCode());

        // When requesting a page for future time the draft in workflow is not returned
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => '2016-06-14 00:00:01',
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());
    }

    /**
     * Draft pages that have progressed through a workflow and are in the publish queue are
     * returned for future state queries.
     */
    public function testDraftInQueue()
    {
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'embargoOnly'));

        // Currently in the publish queue
        $this->assertTrue($draft->PublishJobID > 0);

        // Check that there is no live version
        $this->assertTrue($draft->isOnDraft());
        $this->assertFalse($draft->isPublished());

        // Request for date after publish returns draft
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $afterDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request for date before publish returns nothing
        $beforeDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('-1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $beforeDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());
    }

    /**
     * When multiple drafts exist the latest is the one returned if any are returned.
     */
    public function testMultipleDrafts()
    {
        $draft = $this->objFromFixture('SiteTree', 'multiDraft');

        // Check that there is no live version
        $this->assertTrue($draft->isOnDraft());
        $this->assertFalse($draft->isPublished());

        $draft->Title = 'New title here';
        $draft->write();

        // Finish a workflow for the draft
        $draft->Title = 'Another new title';
        $draft = $this->finishWorkflow($draft);

        $versions = Versioned::get_all_versions('SiteTree', $draft->ID);
        $this->assertEquals($versions->Count(), 3);

        // Get date in the future should be the latest version for this page
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => '2016-06-14 00:00:01',
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals($pages->count(), 1);
        $this->assertEquals($pages->first()->Version, 3);
    }

    /**
     * Drafts that are embargoed are returned from and including the desired embargo date.
     */
    public function testDraftEmbargo()
    {
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'embargoOnly'));

        // Check that there is no live version
        $this->assertTrue($draft->isOnDraft());
        $this->assertFalse($draft->isPublished());

        // Request future state for now which is a mocked date
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => DBDatetime::now(),
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());

        // Request future state for embargo
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $draft->PublishOnDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request future state for after embargo
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $afterDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);
    }

    /**
     * Drafts that are expired are returned up to and including the desired expiry date.
     */
    public function testDraftExpiry()
    {
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'expiryOnly'));

        // At the end of the workflow this page is published immediately
        $this->assertTrue($draft->isPublished());

        // Request future state for now which is a mocked date
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => DBDatetime::now(),
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request future state for expiry
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $draft->UnPublishOnDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request future state for after expiry
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->UnPublishOnDate)
            ->modify('+1 hour')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $afterDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());
    }

    /**
     * Drafts are returned for dates that fall inside their embargo expiry.
     */
    public function testDraftEmbargoExpiry()
    {
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'embargoAndExpiry'));

        // Check that there is no live version after workflow
        $this->assertTrue($draft->isOnDraft());
        $this->assertFalse($draft->isPublished());

        // Request future state for now which is before embargo
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => DBDatetime::now(),
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());

        // Request future state for after embargo and before expiry
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('+6 hour')
            ->format('Y-m-d H:i:s');
        $this->assertTrue(strtotime($afterDate) < strtotime($draft->UnPublishOnDate));
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $afterDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request future state for after expiry
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->UnPublishOnDate)
            ->modify('+6 hour')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $afterDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());
    }

    /**
     * Current published record is returned for dates prior to new draft's embargo,
     * otherwise new draft is returned rather than published version.
     */
    public function testPublishedDraftEmbargo()
    {

        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'basic'));
        $title = $draft->Title;
        $this->assertTrue($draft->isPublished());

        // New draft version and embargo with date 4 days later
        $draft->Title = 'New Title';
        $draft->DesiredPublishDate = '2016-06-20 00:00:01';
        $draft = $this->finishWorkflow($draft);

        // Request prior to new embargo which should get live page
        $priorDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('-1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $priorDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($title, $pages->first()->Title);

        // Request after new embargo should get new draft page
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $afterDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);
    }

    /**
     * Current published record is returned for dates that do not match the expiry of the
     * new draft.
     */
    public function testPublishedDraftExpiry()
    {
        // Publish draft
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'expiryOnly'));
        $this->assertTrue($draft->isPublished());

        // New draft version and expiry with date 2 days earlier
        $draft->Title = 'New Title';
        $draft->DesiredUnPublishDate = '2016-06-15 00:00:01';
        $draft = $this->finishWorkflow($draft);

        // Request after the new expiry but before the previously published expiry should get no page
        $priorDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->UnPublishOnDate)
            ->modify('+1 hour')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $priorDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());

        // Request before the new expiry should get new page
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->UnPublishOnDate)
            ->modify('-1 hour')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $afterDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);
    }

    /**
     * Current published record is returned for times outside of the new embargo/expiry period
     * for the new draft page.
     */
    public function testPublishedDraftEmbargoExpiry()
    {
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'distantExpiry'));
        $title = $draft->Title;
        $this->assertTrue($draft->isPublished());

        // New draft version with a shorter embargo/expiry period encompased by current live
        $draft->Title = 'New Title';
        $draft->DesiredPublishDate = '2016-06-22 00:00:01';
        $draft->DesiredUnPublishDate = '2016-06-24 00:00:01';
        $draft = $this->finishWorkflow($draft);

        // Request prior to new embargo date should get live page
        $priorDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('-1 hour')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $priorDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);

        $this->assertEquals(1, $pages->count());
        $this->assertEquals($title, $pages->first()->Title);

        // Request between new embargo/expiry dates should get draft page
        $priorDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('+4 hour')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $priorDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request after new expiry but before live expiry should get 404 as the new draft will have replaced
        // the current live page and have expired by this time
        $priorDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->UnPublishOnDate)
            ->modify('+1 hour')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $priorDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());
    }

    /**
     * When expiry is cleared for a page that is published.
     */
    public function testExpiryCleared()
    {
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'expiryOnly'));
        $title = $draft->Title;
        $this->assertTrue($draft->isPublished());
        $this->assertEquals('2016-06-17 00:00:01', $draft->UnPublishOnDate);

        // New version with embargo no expiry
        $draft->Title = 'New Change to Title';
        $draft->DesiredPublishDate = '2016-06-15 00:00:01';
        $draft->DesiredUnPublishDate = '';
        $draft = $this->finishWorkflow($draft);

        // Request prior to new publish date should get live
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('-1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $date,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($title, $pages->first()->Title);

        // Request after new publish date should get draft
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $date,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request after published unpublish date should get draft
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', '2016-06-17 00:00:01')
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $afterDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);
    }

    /**
     * When embargo is cleared for a page which is in the queue to be published.
     */
    public function testEmbargoCleared()
    {
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'embargoOnly'));
        $this->assertFalse($draft->isPublished()); // In the queue waiting
        $this->assertEquals('2016-06-15 00:00:01', $draft->PublishOnDate);

        // New version with expiry no embargo
        $draft->Title = 'New Change to Title';
        $draft->DesiredPublishDate = '';
        $draft->DesiredUnPublishDate = '2016-06-18 00:00:01';
        $draft = $this->finishWorkflow($draft);
        $this->assertTrue($draft->isPublished());

        // Request prior to previous embargo should get new version
        $date = DateTime::createFromFormat('Y-m-d H:i:s', '2016-06-15 00:00:01')
            ->modify('-1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $date,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request after previous embargo date should get new version
        $date = DateTime::createFromFormat('Y-m-d H:i:s', '2016-06-15 00:00:01')
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $date,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request after new expiry date should get nothing
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $draft->UnPublishOnDate)
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $date,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());
    }

    /**
     * When embargo and expiry are both cleared the new version is returned.
     */
    public function testEmbargoAndExpiryCleared()
    {
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'embargoAndExpiry'));
        $this->assertFalse($draft->isPublished()); // In the queue waiting
        $this->assertEquals('2016-06-18 00:00:01', $draft->PublishOnDate);
        $this->assertEquals('2016-06-19 00:00:01', $draft->UnPublishOnDate);

        // New version with expiry no embargo
        $draft->Title = 'New Change to Title';
        $draft->DesiredPublishDate = '';
        $draft->DesiredUnPublishDate = '';
        $draft = $this->finishWorkflow($draft);
        $this->assertTrue($draft->isPublished());

        // Request prior to previous embargo should get new version
        $date = DateTime::createFromFormat('Y-m-d H:i:s', '2016-06-18 00:00:01')
            ->modify('-1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $date,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request after previous embargo date should get new version
        $date = DateTime::createFromFormat('Y-m-d H:i:s', '2016-06-18 00:00:01')
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $date,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request after previous expiry should get new version
        $date = DateTime::createFromFormat('Y-m-d H:i:s', '2016-06-19 00:00:01')
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $date,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);
    }

    /**
     * The helper to return links in a future state format.
     */
    public function testFutureStateLink()
    {
        $draft = $this->objFromFixture('SiteTree', 'wideEmbargoAndExpiry');
        $preview = $draft->PreviewLink();

        $link = $draft->getFutureTimeLink($draft->DesiredPublishDate);
        $this->assertEquals(str_replace($preview, '', $link), '?stage=Stage&ft=20160620T0000');

        $link = $draft->getFutureTimeLink($draft->DesiredUnPublishDate);
        $this->assertEquals(str_replace($preview, '', $link), '?stage=Stage&ft=20160629T0000');

        $link = $draft->getFutureTimeLink('2016-06-17T0000');
        $this->assertEquals(str_replace($preview, '', $link), '?stage=Stage&ft=20160617T0000');

        $link = $draft->getFutureTimeLink('2016-06-17 00:00:00');
        $this->assertEquals(str_replace($preview, '', $link), '?stage=Stage&ft=20160617T0000');

        $link = $draft->getFutureTimeLink('2016-06-17 00:00');
        $this->assertEquals(str_replace($preview, '', $link), '?stage=Stage&ft=20160617T0000');

        $link = $draft->getFutureTimeLink('2016-06-17');
        $this->assertEquals(str_replace($preview, '', $link), '?stage=Stage&ft=20160617T0000');

        $link = $draft->getFutureTimeLink('2016-06');
        $this->assertEquals(str_replace($preview, '', $link), '?stage=Stage&ft=20160601T0000');

        $link = $draft->getFutureTimeLink('');
        $this->assertEquals($link, null);
    }

    /**
     * Archived pages do not have entries in the SiteTree or SiteTree_Live tables and should be ignored.
     */
    public function testArchivedPagesIgnored()
    {
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'expiryOnly'));

        // At the end of the workflow this page is published immediately
        $this->assertTrue($draft->isPublished());

        // Request future state for now which is a mocked date
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => DBDatetime::now(),
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Log in as admin so can archive the page
        $this->logInWithPermission();
        $draft->doArchive();

        $this->assertFalse($draft->isPublished());
        $this->assertFalse($draft->isOnDraft());

        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => DBDatetime::now(),
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());
    }

    /**
     * Pages deleted from draft only use published versions for future state.
     */
    public function testDeletedFromDraftPagesIgnored()
    {
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'basic'));
        $title = $draft->Title;
        $this->assertTrue($draft->isPublished());

        // New draft version and embargo with date several days later
        $draft->Title = 'New Title';
        $draft->DesiredPublishDate = '2016-06-20 00:00:01';
        $draft = $this->finishWorkflow($draft);

        // Request prior to new embargo which should get live page
        $priorDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('-1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $priorDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($title, $pages->first()->Title);

        // Request after new embargo should get new draft page
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $afterDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Log in as admin and delete page from draft
        $this->logInWithPermission();
        $draft->deleteFromStage(Versioned::DRAFT);

        $this->assertTrue($draft->isPublished());
        $this->assertFalse($draft->isOnDraft());

        // Request after new embargo should get live page as new draft has been removed
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->PublishOnDate)
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $afterDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($title, $pages->first()->Title);
    }

    /**
     * Unpublished pages are not included as they have been removed from the _Live table.
     */
    public function testUnpublishedPagesIgnored()
    {
        $draft = $this->finishWorkflow($this->objFromFixture('SiteTree', 'basic'));
        $title = $draft->Title;

        $this->assertTrue($draft->isPublished());
        $this->assertTrue($draft->isOnDraft());

        // Request should get current live as there is no embargo
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => DBDatetime::now()->getValue(),
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($title, $pages->first()->Title);

        // Remove page from _Live table
        $this->logInWithPermission();
        $draft->deleteFromStage(Versioned::LIVE);

        $this->assertFalse($draft->isPublished());
        $this->assertTrue($draft->isOnDraft());

        // Request should get no results as page has moved back to draft and is not queued up
        // any longer
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => DBDatetime::now()->getValue(),
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());
    }
}

/**
 * For creating a state for a page where it is currently in the middle of a workflow.
 */
class WorkflowFutureStateTest_DummyWorkflowAction extends WorkflowAction
{
    public function execute(WorkflowInstance $workflow) {
        return false;
    }
}
