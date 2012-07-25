<?php
/**
 * Tests for the workflow engine.
 *
 * @author     marcus@silverstripe.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage tests
 */
class WorkflowEngineTest extends SapphireTest {

	public function testCreateWorkflowInstance() {
		
		$definition = new WorkflowDefinition();
		$definition->Title = "Create Workflow Instance";
		$definition->write();

		$stepOne = new WorkflowAction();
		$stepOne->Title = "Step One";
		$stepOne->WorkflowDefID = $definition->ID;
		$stepOne->write();

		$stepTwo = new WorkflowAction();
		$stepTwo->Title = "Step Two";
		$stepTwo->WorkflowDefID = $definition->ID;
		$stepTwo->write();

		$transitionOne = new WorkflowTransition();
		$transitionOne->Title = 'Step One T1';
		$transitionOne->ActionID = $stepOne->ID;
		$transitionOne->NextActionID = $stepTwo->ID;
		$transitionOne->write();

		$instance = new WorkflowInstance();
		$instance->write();

		$instance->beginWorkflow($definition);

		$actions = $definition->Actions();
		$this->assertEquals(2, $actions->Count());

		$transitions = $actions->find('Title', 'Step One')->Transitions();
		$this->assertEquals(1, $transitions->Count());
	}

	public function testExecuteImmediateWorkflow() {
		$def = $this->createDefinition();

		$actions = $def->Actions();
		$firstAction = $def->getInitialAction();
		$this->assertEquals('Step One', $firstAction->Title);

		$instance = new WorkflowInstance();
		$instance->beginWorkflow($def);
		$this->assertTrue($instance->CurrentActionID > 0);

		$instance->execute();

		// the instance should be complete, and have two finished workflow action
		// instances.
		$actions = $instance->Actions();
		$this->assertEquals(2, $actions->Count());

		foreach($actions as $action) {
			$this->assertTrue((bool) $action->Finished);
		}
	}
	
	public function testPublishAction() {
		$this->logInWithPermission();
		
		$action = new PublishItemWorkflowAction;
		$instance = new WorkflowInstance();

		$page = new Page();
		$page->Title = 'stuff';
		$page->write();

		$instance->TargetClass = 'Page';
		$instance->TargetID = $page->ID;

		$this->assertFalse($page->isPublished());

//		$this->assertTrue($page->Status == 'New');

		$action->execute($instance);

		$page = DataObject::get_by_id('Page', $page->ID);
		$this->assertTrue($page->isPublished());
		
	}

	protected function createDefinition() {
		$definition = new WorkflowDefinition();
		$definition->Title = "Dummy Workflow Definition";
		$definition->write();

		$stepOne = new WorkflowAction();
		$stepOne->Title = "Step One";
		$stepOne->WorkflowDefID = $definition->ID;
		$stepOne->write();

		$stepTwo = new WorkflowAction();
		$stepTwo->Title = "Step Two";
		$stepTwo->WorkflowDefID = $definition->ID;
		$stepTwo->write();

		$transitionOne = new WorkflowTransition();
		$transitionOne->Title = 'Step One T1';
		$transitionOne->ActionID = $stepOne->ID;
		$transitionOne->NextActionID = $stepTwo->ID;
		$transitionOne->write();

		return $definition;
	}

}