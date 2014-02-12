<?php
/**
 * Handles interactions triggered by users in the backend of the CMS. Replicate this
 * type of functionality wherever you need UI interaction with workflow. 
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class AdvancedWorkflowExtension extends LeftAndMainExtension {

	public function startworkflow($data, $form, $request) {
		$item = $form->getRecord();

		if (!$item || !$item->canEdit()) {
			return;
		}
		
		// Save a draft, if the user forgets to do so
		$this->saveAsDraftWithAction($form, $item);

		$svc = singleton('WorkflowService');
		$svc->startWorkflow($item);

		return $this->owner->getResponseNegotiator()->respond($this->owner->getRequest());
	}

	/**
	 * Need to update the edit form AFTER it's been transformed to read only so that the workflow stuff is still
	 * allowed to be added with 'write' permissions
	 *
	 * @param Form $form
	 */
	public function updateEditForm(Form $form) {
		$svc    = singleton('WorkflowService');
		$p      = $form->getRecord();
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

			$fields->findOrMakeTab(
				'Root.WorkflowActions',
				_t('Workflow.WorkflowActionsTabTitle', 'Workflow Actions')
			);
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
	 * @todo refactor with WorkflowInstance::updateWorkflow
	 * 
	 * @param array $data
	 * @param Form $form
	 * @param SS_HTTPRequest $request
	 * @return String
	 */
	public function updateworkflow($data, Form $form, $request) {
		$svc = singleton('WorkflowService');
		$p = $form->getRecord();
		$workflow = $svc->getWorkflowFor($p);
		$action = $workflow->CurrentAction();

		if (!$p || !$p->canEditWorkflow()) {
			return;
		}

		$allowedFields = $workflow->getWorkflowFields()->saveableFields();
		unset($allowedFields['TransitionID']);

		$allowed = array_keys($allowedFields);
		if (count($allowed)) {
			$form->saveInto($action, $allowed);
			$action->write();
		}

		if (isset($data['TransitionID']) && $data['TransitionID']) {
			$svc->executeTransition($p, $data['TransitionID']);
		} else {
			// otherwise, just try to execute the current workflow to see if it
			// can now proceed based on user input
			$workflow->execute();
		}

		return $this->owner->getResponseNegotiator()->respond($this->owner->getRequest());
	}
	
	/**
	 * Ocassionally users forget to apply their changes via the standard CMS "Save Draft" button,
	 * and select the action button instead - losing their changes.
	 * Calling this from a controller method saves a draft automatically for the user, whenever a workflow action is run.
	 * See: #72 and #77
	 * 
	 * @param \Form $form
	 * @param \DataObject $item
	 * @return void
	 */
	protected function saveAsDraftWithAction(Form $form, DataObject $item) {
		$form->saveInto($item);
		$item->write();
	}	

}
