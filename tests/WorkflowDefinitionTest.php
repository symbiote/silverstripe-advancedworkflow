<?php

class WorkflowDefinitionTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testUseSpecifiedTitleOrFallbackToDefault()
    {
        $definition = new WorkflowDefinition;
        $definition->Title = 'My definition';
        $definition->write();

        $this->assertSame('My definition', $definition->Title, 'Set title is respected on write');

        WorkflowDefinition::$default_workflow_title_base = 'My Workflow';
        $definition2 = new WorkflowDefinition;
        $definition2->write();

        // Check the start, because a number is appended to the end which could vary from other fixtured data
        $this->assertStringStartsWith(
            'My Workflow',
            $definition2->Title,
            'Unset title is assigned a default on write'
        );
    }
}
