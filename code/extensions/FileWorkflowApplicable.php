<?php
/**
 * WorkflowApplicable extension specifically for File objects, which don't have the same CMS
 * UI structure so need to be handled a little differently
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class FileWorkflowApplicable extends WorkflowApplicable {
	
	public function updateSummaryFields(&$fields) {
		$fields['ID'] = 'ID';
		$fields['ParentID'] = 'ParentID';
	}

	public function updateCMSFields(FieldList $fields) {
		parent::updateCMSFields($fields);
		
		$svc = singleton('WorkflowService');
		
		// add the workflow fields directly. It's a requirement of workflow on file objects
		// that CMS admins mark the workflow step as being editable for files to be administerable
		$active = $svc->getWorkflowFor($this->owner);
		if ($active) {
			$current = $active->CurrentAction();
			$wfFields = $active->getWorkflowFields();
			
			// loading data in a somewhat hack way
			$form = new Form($this, 'DummyForm', $wfFields, new FieldList());
			$form->loadDataFrom($current);

			$fields->addFieldsToTab('BottomRoot.WorkflowActions', $wfFields);
		}
	}

	public function onAfterWrite() {
		parent::onAfterWrite();
		
		$svc = singleton('WorkflowService');
		$workflow = $svc->getWorkflowFor($this->owner);
		$rawData = $this->owner->toMap();
		if ($workflow && $this->owner->TransitionID) {
			// we want to transition, so do so if that's a valid transition to take. 
			$action = $workflow->CurrentAction();
			if (!$this->canEditWorkflow()) {
				return;
			}

			$allowedFields = $workflow->getWorkflowFields()->saveableFields();
			unset($allowedFields['TransitionID']);

			$allowed = array_keys($allowedFields);
			
			foreach ($allowed as $field) {
				if (isset($rawData[$field])) {
					$action->$field = $rawData[$field];
				}
			}
			
			$action->write();

			if (isset($rawData['TransitionID']) && $rawData['TransitionID']) {
				// unset the transition ID so this doesn't get re-executed
				$this->owner->TransitionID = null;
				$svc->executeTransition($this->owner, $rawData['TransitionID']);
			} else {
				// otherwise, just try to execute the current workflow to see if it
				// can now proceed based on user input
				$workflow->execute();
			}
		}
	}
}