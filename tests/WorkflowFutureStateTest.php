<?php

use SilverStripe\Model\FieldType\DBDatetime;

class WorkflowFutureStateTest extends FunctionalTest {

    protected static $fixture_file = 'workflowfuturestate.yml';

    protected $requiredExtensions = array(
        'SiteTree' => array(
            'WorkflowEmbargoExpiryExtension',
            'Versioned',
        )
    );

    protected $illegalExtensions = array(
        'SiteTree' => array(
            'Translatable'
        )
    );

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

        // Another way to test the same thing
        $pages = SiteTree::get()
            ->setDataQueryParam([
                'Versioned.stage' => Versioned::LIVE
            ]);
        $this->assertEquals(0, $pages->count());

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
     * Draft pages that are in a workflow are returned for future state queries.
     * This essentially tests blank embargo and expiry dates for a page which infer immediate publish
     * and never unpublish.
     */
    public function testDraftInWorkflow()
    {
        $draft = $this->objFromFixture('SiteTree', 'inWorkflow');

        // Check that there is no live version
        $this->assertTrue($draft->isOnDraft());
        $this->assertFalse($draft->isPublished());

        // No live page exists
        $res = $this->get($draft->Link());
        $this->assertEquals(404, $res->getStatusCode());

        // When requesting a page for future time the draft in workflow is returned
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => '2016-06-14 00:00:01',
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
    }

    /**
     * Draft pages that have progressed through a workflow and are in the publish queue are
     * returned for future state queries.
     */
    public function testDraftInQueue()
    {
        // Dummy PublishOnDate set on this record in order to create a job for it
        $draft = $this->objFromFixture('SiteTree', 'inQueue');
        $this->assertTrue($draft->PublishJobID > 0);

        // Check that there is no live version
        $this->assertTrue($draft->isOnDraft());
        $this->assertFalse($draft->isPublished());

        // No live page exists
        $res = $this->get($draft->Link());
        $this->assertEquals(404, $res->getStatusCode());

        // When requesting a page for future time the draft in queue is returned
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => '2016-06-14 00:00:01',
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
    }

    /**
     * When multiple drafts exist the latest is the one returned if any are returned.
     */
    public function testMultipleDrafts()
    {
        $draft = $this->objFromFixture('SiteTree', 'inWorkflow');

        // Check that there is no live version
        $this->assertTrue($draft->isOnDraft());
        $this->assertFalse($draft->isPublished());

        $draft->Title = 'New title here';
        $draft->write();

        $versions = Versioned::get_all_versions('SiteTree', $draft->ID);

        // Write one more draft with a publish job attched
        $draft->PublishOnDate = '2020-01-01 00:00:00';
        $draft->write();

        $this->assertEquals($versions->Count(), 3);

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
        $draft = $this->objFromFixture('SiteTree', 'embargoOnly');

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
                'Future.time' => $draft->DesiredPublishDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request future state for after embargo
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->DesiredPublishDate)
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
        $draft = $this->objFromFixture('SiteTree', 'expiryOnly');

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
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request future state for expiry
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $draft->DesiredUnPublishDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request future state for after expiry
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->DesiredUnPublishDate)
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
        $draft = $this->objFromFixture('SiteTree', 'embargoAndExpiry');

        // Check that there is no live version
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
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->DesiredPublishDate)
            ->modify('+6 hour')
            ->format('Y-m-d H:i:s');
        $this->assertTrue(strtotime($afterDate) < strtotime($draft->DesiredUnPublishDate));
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $afterDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($draft->Title, $pages->first()->Title);

        // Request future state for after expiry
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->DesiredUnPublishDate)
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
     * other new draft is returned rather than published version.
     */
    public function testPublishedDraftEmbargo()
    {
        // Publish draft
        $draft = $this->objFromFixture('SiteTree', 'embargoOnly');
        $title = $draft->Title;
        $draft->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $this->assertTrue($draft->isPublished());

        // New draft version and embargo with date 4 days later
        $draft->Title = 'New Title';
        $draft->DesiredPublishDate = '2016-06-20 00:00:01';
        $draft->write();

        // Request prior to new embargo which should get live page
        $priorDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->DesiredPublishDate)
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
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->DesiredPublishDate)
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
        $draft = $this->objFromFixture('SiteTree', 'expiryOnly');
        $title = $draft->Title;
        $draft->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $this->assertTrue($draft->isPublished());

        // New draft version and expiry with date 2 days earlier
        $draft->Title = 'New Title';
        $draft->DesiredUnPublishDate = '2016-06-15 00:00:01';
        $draft->write();

        // Request after the new expiry but before the published expiry should get live page
        $priorDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->DesiredUnPublishDate)
            ->modify('+1 hour')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $priorDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($title, $pages->first()->Title);

        // Request before the new expiry should get the draft page
        $afterDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->DesiredUnPublishDate)
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
        // Publish draft
        $draft = $this->objFromFixture('SiteTree', 'wideEmbargoAndExpiry');
        $title = $draft->Title;
        $draft->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $this->assertTrue($draft->isPublished());

        // New draft version and expiry with a shorter embargo/expiry period encompased by current live
        $draft->Title = 'New Title';
        $draft->DesiredPublishDate = '2016-06-22 00:00:01';
        $draft->DesiredUnPublishDate = '2016-06-24 00:00:01';
        $draft->write();

        // Request prior to new embargo date should get live page
        $priorDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->DesiredPublishDate)
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
        $priorDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->DesiredPublishDate)
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

        // Request after new expiry should get current live page
        $priorDate = DateTime::createFromFormat('Y-m-d H:i:s', $draft->DesiredUnPublishDate)
            ->modify('+1 hour')
            ->format('Y-m-d H:i:s');
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => $priorDate,
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
        $this->assertEquals($title, $pages->first()->Title);
    }

    /**
     * The feature flag so that only pages in the queue (i.e: have passed workflow) are used for future state.
     */
    public function testFeatureFlag()
    {
        Config::inst()->update('WorkflowEmbargoExpiryExtension', 'future_state_trigger', 'workflow_end');

        $draft = $this->objFromFixture('SiteTree', 'inWorkflow');

        // Page in workflow only will not be returned
        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => '2016-06-14 00:00:01',
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(0, $pages->count());

        // Page in queue will be returned
        $draft = $this->objFromFixture('SiteTree', 'inQueue');
        $this->assertTrue($draft->PublishJobID > 0);

        $pages = SiteTree::get()
            ->filter('ID', $draft->ID)
            ->setDataQueryParam([
                'Future.time' => '2016-06-14 00:00:01',
                'Versioned.stage' => Versioned::DRAFT
            ]);
        $this->assertEquals(1, $pages->count());
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
}
