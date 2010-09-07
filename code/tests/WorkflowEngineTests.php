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
		$transitionOne->NextStepID = $stepTwo->ID;
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
}