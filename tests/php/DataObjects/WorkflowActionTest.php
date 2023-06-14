<?php

namespace Symbiote\AdvancedWorkflow\Tests\DataObjects;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowAction;

class WorkflowActionTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testAdminUsersCanAlwaysEdit()
    {
        $this->logInWithPermission('ADMIN');
        $action = new WorkflowAction();
        $this->assertTrue($action->canEditTarget(new DataObject));
    }
}
