<?php
/**
 * Handles interactions triggered by users in the backend of the CMS. Replicate this
 * type of functionality wherever you need UI interaction with workflow. 
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class AdvancedWorkflowExtension extends LeftAndMainDecorator {
    public function startworkflow($data, $form, $request) {
		$p = $this->owner->getRecord($this->owner->currentPageID());
		if (!$p || !$p->canEdit()) {
			return;
		}
		$item = DataObject::get_by_id('SiteTree', (int) $data['ID']);

		if ($item) {
			$svc = singleton('WorkflowService');
			$svc->startWorkflow($item);
		}

		return $this->javascriptRefresh($data['ID']);
	}

	/**
	 * Need to update the edit form AFTER it's been transformed to read only so that the workflow stuff is still
	 * allowed to be added with 'write' permissions
	 *
	 * @param Form $form
	 */
	public function updateEditForm(Form $form) {
		$svc = singleton('WorkflowService');

		$p = $this->owner->getRecord($this->owner->currentPageID());

		$active = $svc->getWorkflowFor($p);
		if ($active) {
			
			$fields = $form->Fields();
			$current = $active->CurrentAction();
			
			$wfFields = $active->getWorkflowFields(); 
			
			$allowed = array_keys($wfFields->saveableFields());
			$data = array();
			foreach ($allowed as $fieldName) {
				$data[$fieldName] = $current->$fieldName;
			}

			$fields->addFieldsToTab('Root.WorkflowActions', $wfFields);

			$form->loadDataFrom($data);

			if (!$p->canEditWorkflow()) {
				$form->makeReadonly();
			}
		}
	}

	/**
	 * Update a workflow based on user input. 
	 *
	 * @param array $data
	 * @param Form $form
	 * @param SS_HTTPRequest $request
	 * @return String
	 */
	public function updateworkflow($data, Form $form, $request) {
		$svc = singleton('WorkflowService');
		$p = $this->owner->getRecord($this->owner->currentPageID());
		$workflow = $svc->getWorkflowFor($p);
		$action = $workflow->CurrentAction();

		if (!$p || !$p->canEditWorkflow()) {
			return;
		}

		$allowedFields = $workflow->getWorkflowFields()->saveableFields();
		unset($allowedFields['TransitionID']);

		$allowed = array_keys($allowedFields);
		$form->saveInto($action, $allowed);
		$action->write();

		if (isset($data['TransitionID']) && $data['TransitionID']) {
			$svc->executeTransition($p, $data['TransitionID']);
		} else {
			// otherwise, just try to execute the current workflow to see if it
			// can now proceed based on user input
			$workflow->execute();
		}

		return $this->javascriptRefresh($data['ID']);
	}

	protected function javascriptRefresh($nodeId, $message = 'Please wait...') {
		FormResponse::add("$('Form_EditForm').resetElements(); $('sitetree').getTreeNodeByIdx(\"$nodeId\").selectTreeNode();");
		FormResponse::status_message($message, "good");
		return FormResponse::respond();
	}
}
