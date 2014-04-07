<?php
/**
 * A workflow action attached to a {@link WorkflowInstance} that has been run,
 * and is either currently running, or has finished.
 * 
 * Each step of the workflow has one of these created for it - it refers back
 * to the original action definition, but is unique for each step of the
 * workflow to ensure re-entrant behaviour. 
 * 
 * @method WorkflowInstance Workflow()
 * @method WorkflowAction BaseAction()
 * @method Member Member()
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
		'BaseAction.Title',
		'Comment',
		'Created',
		'Member.Name',
	);

	public function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);
		$labels['BaseAction.Title'] = _t('WorkflowActionInstance.Title', 'Title');
		$labels['Comment'] = _t('WorkflowAction.CommentLabel', 'Comment');
		$labels['Member.Name'] = _t('WorkflowAction.Author', 'Author');
		$labels['Finished'] = _t('WorkflowAction.FinishedLabel', 'Finished');
		$labels['BaseAction.Title'] = _t('WorkflowAction.TITLE', 'Title');

		return $labels;
	}
	
	/**
	 * Gets fields for when this is part of an active workflow
	 * 
	 * @param FieldList $fields
	 */
	public function updateWorkflowFields($fields) {
		$this->BaseAction()->updateWorkflowFields($fields);
	}
	
	public function updateFrontendWorkflowFields($fields){
		$this->BaseAction()->updateFrontendWorkflowFields($fields, $this->Workflow());
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
	 * @return ArrayList
	 */
	public function getValidTransitions() {
		return $this
			->BaseAction()
			->getValidTransitions($this->Workflow());
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
		$this->Finished = true;
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
	
	public function canView($member = null) {
		return $this->Workflow()->canView($member);
	}
	
	public function canEdit($member = null) {
		return $this->Workflow()->canEdit($member);
	}
	
	public function canDelete($member = null) {
		return $this->Workflow()->canDelete($member);
	}
}