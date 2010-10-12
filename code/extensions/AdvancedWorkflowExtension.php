<?php
/**
 * Handles interactions triggered by users in the backend. 
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
			
			$wfFields = $this->getWorkflowFieldsFor($active);
			
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
	 * @param WorkflowInstance $workflow
	 */
	protected function getWorkflowFieldsFor($workflow) {
		$action    = $workflow->CurrentAction();
		$options   = $action->getValidTransitions();
		$wfOptions = $options->map('ID', 'Title', ' ');
		$fields    = new FieldSet();

		$fields->push(new HeaderField('WorkflowHeader', $action->Title));
		$fields->push(new DropdownField('TransitionID', _t('WorkflowApplicable.NEXT_ACTION', 'Next Action'), $wfOptions));

		$action->BaseAction()->updateWorkflowFields($fields);

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
		$svc = singleton('WorkflowService');
		$p = $this->owner->getRecord($this->owner->currentPageID());
		$workflow = $svc->getWorkflowFor($p);
		$action = $workflow->CurrentAction();

		if (!$p || !$p->canEditWorkflow()) {
			return;
		}

		$allowedFields = $this->getWorkflowFieldsFor($workflow)->saveableFields();
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
