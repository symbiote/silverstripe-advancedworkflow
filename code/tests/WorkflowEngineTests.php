<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * Description
 *
 * @author marcus@silverstripe.com.au
 */
class WorkflowEngineTests extends SapphireTest {
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

		$actions = $instance->Actions();
		$this->assertEquals(2, $actions->Count());

		foreach ($actions as $action) {
			$this->assertNotEquals($stepOne->ID, $action->ID);
			$this->assertNotEquals($stepTwo->ID, $action->ID);

			if ($action->Title == 'Step One') {
				$transitions = $action->getAllTransitions();
				$this->assertEquals(1, $transitions->Count());
				$t = $transitions->First();
				$this->assertNotEquals($transitionOne->ID, $t->ID);
				$this->assertEquals($transitionOne->Title, $t->Title);
			}
		}
	}

	public function testExecuteImmediateWorkflow() {
		$def = $this->createDefinition();

		$actions = $def->Actions();
		$firstAction = $actions->First();
		$this->assertEquals('Step One', $firstAction->Title);

		$instance = new WorkflowInstance();
		$instance->beginWorkflow($def);

		$newActions = $instance->Actions();

		$this->assertNotNull($newActions);
		$this->assertEquals(2, $newActions->Count());

		$this->assertNotEquals($firstAction->ID, $instance->CurrentActionID);
		$this->assertTrue($instance->CurrentActionID > 0);
		$instance->execute();

		$this->assertEquals('Complete', $instance->WorkflowStatus);

		// the instance should now be complete, and all actions should be marked as executed
		$actions = $instance->Actions();
		$this->assertEquals(2, $actions->Count());
		foreach ($actions as $action) {
			if ($action->Executed) {
				$this->assertTrue(true);
			}
		}
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