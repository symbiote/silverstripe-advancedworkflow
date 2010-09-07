<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * A WorkflowInstance is created whenever a user wants to 'publish' or
 *
 * @author marcus@silverstripe.com.au
 */
class WorkflowInstance extends DataObject {
    public static $db = array(
		'Title' => 'Varchar(128)',
	);

	public static $has_one = array(
		'Definition' => 'WorkflowDefinition',
		'CurrentAction' => 'WorkflowAction',
	);
	
	public static $has_many = array(
		'Actions' => 'WorkflowAction',
	);

	public function beginWorkflow(WorkflowDefinition $definition) {
		$this->Title = sprintf(_t('WorkflowInstance.TITLE_STUB', 'Instance #%s of %s'), $this->ID, $definition->Title);

		$actionMapping = array();

		$actions = $definition->Actions();
		if ($actions) {
			foreach ($actions as $action) {
				$newAction = $action->duplicate(false);
				$newAction->WorkflowDefID = 0;
				$newAction->WorkflowID = $this->ID;
				$newAction->write();

				$actionMapping[$action->ID] = $newAction->ID;
			}

			// iterate again, so we can clone the action transitions with appropriate
			// mappings

			foreach ($actions as $action) {
				$transitions = $action->getAllTransitions();
				if ($transitions) {
					foreach ($transitions as $transition) {
						$newTransition = $transition->duplicate(false);
						$newTransition->ActionID = $actionMapping[$transition->ActionID];
						$newTransition->NextStepID = $actionMapping[$transition->NextStepID];
						$newTransition->write();
					}
				}
			}
		}

		

	}
}

