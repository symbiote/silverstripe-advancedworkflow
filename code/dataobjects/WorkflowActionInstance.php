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
		'BaseAction.Title'		=> 'Title',
		'Comment'				=> 'Comment',
		'Created'				=> 'Date',
		'Member.Name'			=> 'Author'
	);
	
	public static $field_labels = array(
		'BaseAction.Title'		=> 'Title',
		'Comment'				=> 'Comment',
		'Created'				=> 'Date',
		'Member.Name'			=> 'Author'
	);
	
	/**
	 * Gets fields for when this is part of an active workflow
	 */
	public function updateWorkflowFields($fields) {
		if ($this->BaseAction()->AllowCommenting) {	
			$fields->push(new TextareaField('Comment', _t('WorkflowAction.COMMENT', 'Comment')));
		}
	}
	
	public function updateFrontendWorkflowFields($fields){
		if ($this->BaseAction()->AllowCommenting) {		
			$fields->push(new TextareaField('WorkflowActionInstanceComment', _t('WorkflowAction.FRONTENDCOMMENT', 'Comment')));
		}
		
		$ba = $this->BaseAction();
		$fields = $ba->updateFrontendWorkflowFields($fields, $this->Workflow());	
	}

	/**
	 * Gets Front-End DataObject
	 * 
	 * Use the DataObject as defined in the WorkflowAction, otherwise fall back to the
	 * context object.
	 * 
	 * Useful for situations where front end workflow deals with multiple data objects
	 * 
	 * @return DataObject
	 */
	public function getFrontEndDataObject() {
		$obj = null;
		$ba = $this->BaseAction();
		
		if ($ba->hasMethod('getFrontEndDataObject')) {
			$obj = $ba->getFrontEndDataObject();
		} else {
			$obj = $this->Workflow()->getTarget();
		}
		
		return $obj;
	}
	
	public function updateFrontEndWorkflowActions($actions) {
		$ba = $this->BaseAction();
		
		if ($ba->hasMethod('updateFrontEndWorkflowActions')) {
			$ba->updateFrontEndWorkflowActions($actions);
		}
	}
	
	public function getRequiredFields() {
		$validator = null;
		$ba = $this->BaseAction();
		
		if ($ba->hasMethod('getRequiredFields')) {
			$validator = $ba->getRequiredFields();
		}
		
		return $validator;
	}

	public function setFrontendFormRequirements() {
		$ba = $this->BaseAction();
		
		if ($ba->hasMethod('setFrontendFormRequirements')) {
			$ba->setFrontendFormRequirements();
		}
	}
	
	public function doFrontEndAction(array $data, Form $form, SS_HTTPRequest $request) {
		//Save Front End Workflow notes, then hand over to Workflow Action
		if (isset($data["WorkflowActionInstanceComment"])) {
			$this->Comment = $data["WorkflowActionInstanceComment"];
			$this->write();
		}
		
		$ba = $this->BaseAction();
		if ($ba->hasMethod('doFrontEndAction')) {
			$ba->doFrontEndAction($data, $form, $request);
		}
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
		$valid     = new ArrayList();

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
		$this->MemberID = Member::currentUserID();
		$this->write();
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