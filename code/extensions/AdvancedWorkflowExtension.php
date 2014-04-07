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
	 * Workflow provider service
	 * 
	 * @return WorkflowService
	 */
	protected function workflowService() {
		return singleton('WorkflowService');
	}

	/**
	 * Need to update the edit form AFTER it's been transformed to read only so that the workflow stuff is still
	 * allowed to be added with 'write' permissions
	 *
	 * @param Form $form
	 */
	public function updateEditForm(Form $form) {
		$service = $this->workflowService();
		$record = $form->getRecord();

		// Determine if loading from active step, or from definition
		if ($active = $service->getWorkflowFor($record)) {
			// Load active workflow
			$current = $active->CurrentAction(); // WorkflowActionInstance
			$wfFields = $active->getWorkflowFields();
		} elseif($effective = $service->getDefinitionFor($record)) {
			// Request fielids for initial workflow step from effective definition
			$current = null;
			$wfFields = $effective->getWorkflowFields();
		} else {
			return;
		}
		
		// Merge these fields with the form
		$fields = $form->Fields();
		$fields->findOrMakeTab(
			'Root.WorkflowActions',
			_t('Workflow.WorkflowActionsTabTitle', 'Workflow Actions')
		);
		$fields->addFieldsToTab('Root.WorkflowActions', $wfFields);

		// If a step is active, load saved details into the form
		if($current) {
			$allowed = array_keys($wfFields->saveableFields());
			$data = array();
			foreach ($allowed as $fieldName) {
				$data[$fieldName] = $current->$fieldName;
			}
			$form->loadDataFrom($data);
		}

		// Apply workflow specific locking to this form
		if (!$record->canEditWorkflow()) {
			$form->makeReadonly();
		}
	}

	/**
	 * Generates a new workflow based on user input
	 * 
	 * @param array $data
	 * @param Form $form
	 * @param SS_HTTPRequest $request
	 * @return SS_HTTPResponse
	 */
	public function startworkflow($data, $form, $request) {
		// Ensure record is valid and can be processed
		$record = $form->getRecord();
		if (!$record || !$record->canEdit()) {
			return;
		}
		
		// Save a draft, if the user forgets to do so
		$this->saveAsDraftWithAction($form, $record);

		// Initiate workflow
		$service = $this->workflowService();
		$workflow = $service->startWorkflow($record);
		
		// Process workflow from form data
		return $this->processWorkflow($data, $form, $record, $workflow);
	}

	/**
	 * Update a workflow based on user input. 
	 *
	 * @todo refactor with WorkflowInstance::updateWorkflow
	 * 
	 * @param array $data
	 * @param Form $form
	 * @param SS_HTTPRequest $request
	 * @return SS_HTTPResponse
	 */
	public function updateworkflow($data, Form $form, $request) {
		// Ensure record is valid and can be processed
		$record = $form->getRecord();
		if (!$record || !$record->canEditWorkflow()) {
			return;
		}
		
		// Retrieve in-progress workflow
		$service = $this->workflowService();
		$workflow = $service->getWorkflowFor($record);
		
		// Process workflow
		return $this->processWorkflow($data, $form, $record, $workflow);
	}
	
	/**
	 * Advance the process of a workflow, whether in-progress or recently created
	 * 
	 * @param array $data
	 * @param Form $form
	 * @param DataObject $record
	 * @param WorkflowInstance $workflow
	 * @return SS_HTTPResponse
	 */
	protected function processWorkflow($data, $form, $record, $workflow) {
		$action = $workflow->CurrentAction();

		$allowedFields = $workflow->getWorkflowFields()->saveableFields();
		unset($allowedFields['TransitionID']);

		$allowed = array_keys($allowedFields);
		if (count($allowed)) {
			$form->saveInto($action, $allowed);
			$action->write();
		}

		if (!empty($data['TransitionID'])) {
			$service = $this->workflowService();
			$service->executeTransition($record, $data['TransitionID']);
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
