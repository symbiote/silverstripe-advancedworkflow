<?php
/**
 * A workflow action attached to a {@link WorkflowInstance} that has been run,
 * and is either currently running, or has finished.
 * 
 * Each step of the workflow has one of these created for it - it refers back
 * to the original action definition, but is unique for each step of the
 * workflow to ensure re-entrant behaviour. 
 *
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowActionInstance extends DataObject {

	public static $db = array(
		'Comment'  => 'Text',
		'Finished' => 'Boolean'
	);

	public static $has_one = array(
		'Workflow'   => 'WorkflowInstance',
		'BaseAction' => 'WorkflowAction',
		'Member'     => 'Member'
	);
	
	public static $summary_fields = array(
		'Comment',
	);
	
	
	/**
	 * Gets fields for when this is part of an active workflow
	 */
	public function updateWorkflowFields($fields) {
		$fields->push(new TextareaField('Comment', _t('WorkflowAction.COMMENT', 'Comment')));
	}

	/**
	 * Gets the title of this active action instance
	 * 
	 * @return string
	 */
	public function getTitle() {
		return $this->BaseAction()->Title;
	}

	/**
	 * Returns all the valid transitions that lead out from this action.
	 *
	 * This is called if this action has finished, and the workflow engine wants
	 * to run the next action.
	 *
	 * If this action returns only one valid transition it will be immediately
	 * followed; otherwise the user will decide which transition to follow.
	 *
	 * @return DataObjectSet
	 */
	public function getValidTransitions() {
		$available = $this->BaseAction()->Transitions();
		$valid     = new DataObjectSet();

		// iterate through the transitions and see if they're valid for the current state of the item being
		// workflowed
		if($available) foreach($available as $transition) {
			if($transition->isValid($this->Workflow())) $valid->push($transition);
		}

		return $valid;
	}

	/**
	 * Called when this instance is started within the workflow
	 */
	public function actionStart(WorkflowTransition $transition) {
		$this->extend('onActionStart', $transition);
	}

	/**
	 * Called when this action has been completed within the workflow
	 */
	public function actionComplete(WorkflowTransition $transition) {
		$this->extend('onActionComplete', $transition);
	}

	
	/**
	 * Can documents in the current workflow state be edited?
	 *
	 * @param  DataObject $target
	 * @return bool
	 */
	public function canEditTarget(DataObject $target) {
		$absolute = $this->BaseAction()->canEditTarget($target);
		if (!is_null($absolute)) {
			return $absolute;
		}
		switch ($this->BaseAction()->AllowEditing) {
			case 'By Assignees': 
				return $this->Workflow()->canEdit();
			case 'No':
				return false;
			case 'Content Settings':
			default:
				return null;
		}
	}

	/**
	 * Does this action restrict viewing of the document?
	 *
	 * @param  DataObject $target
	 * @return bool
	 */
	public function canViewTarget(DataObject $target) {
		return $this->BaseAction()->canViewTarget($target);
	}

	/**
	 * Does this action restrict the publishing of a document?
	 *
	 * @param  DataObject $target
	 * @return bool
	 */
	public function canPublishTarget(DataObject $target) {
		$absolute = $this->BaseAction()->canPublishTarget($target);
		if (!is_null($absolute)) {
			return $absolute;
		}
		return false;
	}
}