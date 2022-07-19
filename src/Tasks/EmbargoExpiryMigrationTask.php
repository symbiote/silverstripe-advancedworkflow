<?php

namespace Symbiote\AdvancedWorkflow\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use Terraformers\EmbargoExpiry\Extension\EmbargoExpiryExtension;

/**
 * Using this Dev Task:
 *
 * The main thing that you will need to do is tell us what classes you have applied the EmbargoExpiryExtension to. You
 * can do this by update the `classes` config below through yml configuration, or you could copy this Dev Task into
 * your project and update it from there
 *
 * yml example
 * Symbiote\AdvancedWorkflow\Tasks\EmbargoExpiryMigrationTask
 *   classes:
 *     - SilverStripe\CMS\Model\SiteTree
 *     # OR you might have applied it to Page?
 *     - Page
 *     # Plus any other DataObjects you might have applied WorkflowEmbargoExpiry to
 *     - App\Models\MyDataObject
 */
class EmbargoExpiryMigrationTask extends BuildTask
{

    protected $title = 'Migrate Embargo & Expiry Jobs to external module';
    protected $description = 'Migrates existing Embargo & Expiry Jobs to the new Embargo & Expiry module';

    private static string $segment = 'EmbargoExpiryMigrationTask';

    private static array $classes = [];

    /**
     * @param HTTPRequest $request
     * @return void
     */
    public function run($request)
    {
        foreach ($this->config()->get('classes') as $className) {
            if (!DataObject::singleton($className)->hasExtension(EmbargoExpiryExtension::class)) {
                continue;
            }

            /** @var DataList|DataObject[]|EmbargoExpiryExtension[] $dataObjects */
            $dataObjects = DataObject::get($className);

            foreach ($dataObjects as $dataObject) {
                $updated = false;

                if ($dataObject->PublishOnDate) {
                    $dataObject->createOrUpdatePublishJob(strtotime($dataObject->PublishOnDate));

                    $updated = true;
                }

                if ($dataObject->UnPublishOnDate) {
                    $dataObject->createOrUpdateUnPublishJob(strtotime($dataObject->UnPublishOnDate));

                    $updated = true;
                }

                if ($updated) {
                    $dataObject->write();
                }
            }
        }
    }
}
