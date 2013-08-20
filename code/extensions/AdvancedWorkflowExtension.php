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

	/**
	 * Start a workflow
	 * 
	 * @param array $data
	 * @param Form $form
	 * @param SS_HTTPRequest $request
	 * @return void
	 */
	public function startworkflow($data, $form, $request) {
		$item = $form->getRecord();

		if (!$item || !$item->canEdit()) {
			return;
		}

		$allWorkflows = singleton('WorkflowService')->getDefinitionsFor($item);
		$workflowIDs = array_keys($data['action_startworkflow']);
		$workflows = $allWorkflows->filter('ID', $workflowIDs);
		foreach($workflows as $workflow) {
			singleton('WorkflowService')->startWorkflow($item, $workflow);
		}
		return $this->owner->getResponseNegotiator()->respond($this->owner->getRequest());
	}

	/**
	 * Need to update the edit form AFTER it's been transformed to read only so that the workflow stuff is still
	 * allowed to be added with 'write' permissions
	 *
	 * @param Form $form
	 */
	public function updateEditForm(Form $form) {
		$p = $form->getRecord();
		$workflows = singleton('WorkflowService')->getWorkflowsFor($p);

		if(!$workflows->count()) {
			return;
		}

		$fields = $form->Fields();

		$canEdit = true;
		foreach($workflows as $active) {
			if(!$p->canEditWorkflow($active)) {
				$canEdit = false;
			}
			$current = $active->CurrentAction();
			$wfFields = $active->getWorkflowFields();

			$allowed = array_keys($wfFields->saveableFields());
			$data = array();
			foreach($allowed as $fieldName) {
				$data[$fieldName] = $current->$fieldName;
			}
			$fields->addFieldsToTab('Root.WorkflowActions', $wfFields);
		}
		$form->loadDataFrom($data);

		if(!$canEdit) {
			$form->makeReadonly();
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
		$p = $form->getRecord();
		if(!$p) {
			return;
		}

		$allWorkflows = singleton('WorkflowService')->getWorkflowsFor($p);
		$workflowInstanceIDs = array_keys($data['action_updateworkflow']);
		$wfInstances = $allWorkflows->filter('ID', $workflowInstanceIDs);
		foreach($wfInstances as $wfInstance) {
			if(!$p->canEditWorkflow($wfInstance)) {
				continue;
			}
			$action = $wfInstance->CurrentAction();
			$allowedFields = $wfInstance->getWorkflowFields()->saveableFields();
			// Strip the workflow id's from formfields and save them into the Action
			// example: Comment[66] will be $action->Comment
			$allowed = array_keys($allowedFields);
			if(count($allowed)) {
				foreach($allowed as $allowField) {
					$actionFieldName = preg_replace('|\[[^]]*\]|', '', $allowField, -1, $replacementCount);
					if(isset($data[$actionFieldName]) && $actionFieldName != 'TransitionID') {
						if($replacementCount) {
							$action->$actionFieldName = $data[$actionFieldName][$wfInstance->ID];
						} else {
							$action->$actionFieldName = $data[$actionFieldName];
						}
					}
				}
				$action->write();
			}

			if(isset($data['TransitionID']) && $data['TransitionID'][$wfInstance->ID]) {
				singleton('WorkflowService')->executeTransition($p, $wfInstance, $data['TransitionID'][$wfInstance->ID]);
			} else {
				// otherwise, just try to execute the current workflow to see if it
				// can now proceed based on user input
				$wfInstance->execute();
			}
		}

		return $this->owner->getResponseNegotiator()->respond($this->owner->getRequest());
	}

}
