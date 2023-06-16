<?php

namespace Symbiote\AdvancedWorkflow\Tests\Behat\Context;

use PHPUnit\Framework\Assert;
use SilverStripe\BehatExtension\Context\FixtureContext as BaseFixtureContext;
use SilverStripe\Core\Injector\Injector;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowDefinition;
use Symbiote\AdvancedWorkflow\Services\WorkflowService;

if (!class_exists(BaseFixtureContext::class)) {
    return;
}

/**
 * Context used to create fixtures for workflow-related behat tests.
 */
class FixtureContext extends BaseFixtureContext
{
    /**
     * Create a workflow, typically in the background stage of a feature.
     * Example: Given a workflow "My Workflow" using the "Review and Approve" template
     *
     * @Given /^(?:an|a|the) workflow "([^"]+)" using the "([^"]+)" template$/
     */
    public function stepCreateBackgroundWorkflow(string $title, string $template)
    {
        Assert::assertNotNull(
            $this->getWorkflowService()->getNamedTemplate($template),
            "Workflow template named '$template' does not exist."
        );

        if ($existingDefinition = WorkflowDefinition::get()->find('Title', $title)) {
            Assert::assertEquals(
                $template,
                $existingDefinition->Template,
                "A workflow named '$title' already exists, but it doesn't use the '$template' template."
            );
            // If we haven't thrown an exception here, it means the exact workflow we want already exists
            return;
        }

        $definition = WorkflowDefinition::create();
        $definition->Title = $title;
        $definition->Template = $template;
        $definition->write();
    }

    /**
     * Apply a workflow to a record.
     * Example: Given the "page" "About Us" has the "My Workflow" workflow
     *
     * @Given /^(?:an|a|the) "([^"]+)" "([^"]+)" has the "([^"]+)" workflow$/
     */
    public function stepPageHasWorkflow(string $type, string $pageName, string $workflowName)
    {
        $workflow = WorkflowDefinition::get()->find('Title', $workflowName);

        Assert::assertNotNull($workflow, "Workflow named '$workflowName' does not exist.");

        $class = $this->convertTypeToClass($type);
        $fixture = $this->getFixtureFactory()->get($class, $pageName);
        if (!$fixture) {
            $fixture = $this->getFixtureFactory()->createObject($class, $pageName);
        }

        $fixture->WorkflowDefinitionID = $workflow->ID;
        $fixture->write();
    }

    private function getWorkflowService(): WorkflowService
    {
        return Injector::inst()->get(WorkflowService::class);
    }
}
