<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * Handles interactions triggered by users in the backend. 
 *
 * @author marcus@silverstripe.com.au
 */
class ActivityWorkflowExtension extends LeftAndMainDecorator {
    public function startworkflow($data, $form, $request) {
		$item = DataObject::get_by_id('SiteTree', (int) $data['ID']);

		if ($item) {
			$svc = singleton('WorkflowService');
			$svc->startWorkflow($item);
		}

		return $this->javascriptRefresh($data['ID']);
	}


	/**
	 * Need to update the edit form AFTER it's been transformed to read only so that the workflow stuff is still
	 * allowed to be added.
	 *
	 * @param Form $form
	 */
	public function updateEditForm($form) {
		$svc = singleton('WorkflowService');

		$active = $svc->getWorkflowFor($this->owner->getRecord($this->owner->currentPageID()));
		if ($active) {
			$fields = $form->Fields();
			$current = $active->CurrentAction();
			
			$wfFields = $this->getWorkflowFieldsFor($current);

				$fields->addFieldsToTab('Root.WorkflowActions', $wfFields);
		}
	}

	/**
	 * Gets the fields that should be shown for a given action
	 *
	 * @param WorkflowAction $action
	 */
	protected function getWorkflowFieldsFor($action) {
		$options = $action->getNextTransitions();
		$wfOptions = $options->map('ID', 'Title', ' ');
		$fields = new FieldSet();

		$fields->push(new HeaderField('WorkflowHeader', $action->Title));
		$fields->push(new HiddenField('CurrentActionID', '', $action->ID));
		$fields->push(new DropdownField('TransitionID', _t('WorkflowApplicable.NEXT_ACTION', 'Next Action'), $wfOptions));

		$action->updateWorkflowFields($fields);

		return $fields;
	}

	public function updateworkflow($data, Form $form, $request) {
		$action = DataObject::get_by_id('WorkflowAction', $data['CurrentActionID']);
		$allowedFields = $this->getWorkflowFieldsFor($action)->saveableFields();

		unset($allowedFields['CurrentActionID']);
		unset($allowedFields['TransitionID']);

		$allowed = array_keys($allowedFields);
		$form->saveInto($action, $allowed);
		$action->write();
		if (isset($data['TransitionID'])) {
			$svc = singleton('WorkflowService');
			$svc->executeTransition($data['TransitionID']);
		}

		return $this->javascriptRefresh($data['ID']);
	}

	protected function javascriptRefresh($nodeId, $message = 'Please wait...') {
		FormResponse::add("$('sitetree').getTreeNodeByIdx(\"$nodeId\").selectTreeNode();");
		FormResponse::status_message($message, "good");
		return FormResponse::respond();
	}
}