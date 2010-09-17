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
			
			$wfFields = $this->getWorkflowFieldsFor($current);
			
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

	/**
	 * Update a workflow based on user input. 
	 *
	 * @param <type> $data
	 * @param Form $form
	 * @param <type> $request
	 * @return <type>
	 */
	public function updateworkflow($data, Form $form, $request) {
		$p = $this->owner->getRecord($this->owner->currentPageID());
		if (!$p || !$p->canEditWorkflow()) {
			return;
		}

		$action = DataObject::get_by_id('WorkflowAction', $data['CurrentActionID']);
		$allowedFields = $this->getWorkflowFieldsFor($action)->saveableFields();

		unset($allowedFields['CurrentActionID']);
		unset($allowedFields['TransitionID']);

		$allowed = array_keys($allowedFields);
		$form->saveInto($action, $allowed);
		$action->write();

		$svc = singleton('WorkflowService');
		if (isset($data['TransitionID']) && $data['TransitionID']) {
			
			$svc->executeTransition($data['TransitionID']);
		} else {
			// otherwise, just try to execute the current workflow to see if it
			// can now proceed based on user input
			$workflow = $svc->getWorkflowFor($action);
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
