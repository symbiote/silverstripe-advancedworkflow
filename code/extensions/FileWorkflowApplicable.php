<?php
/**
 * WorkflowApplicable extension specifically for File objects, which don't have the same CMS
 * UI structure so need to be handled a little differently. Additionally, it doesn't really 
 * work without custom code to handle the triggering of workflow, and in general is not
 * ready for production use just yet. 
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
		if (!$this->owner->ID) {
			return $fields;
		}
		parent::updateCMSFields($fields);

		// add the workflow fields directly. It's a requirement of workflow on file objects
		// that CMS admins mark the workflow step as being editable for files to be administerable
		$actives = $this->workflowService->getWorkflowsFor($this->owner);
		if($actives) {
			foreach($actives as $active) {
				$current = $active->CurrentAction();
				$wfFields = $active->getWorkflowFields();
				// loading data in a somewhat hack way
				$form = new Form($this, 'DummyForm', $wfFields, new FieldList());
				$form->loadDataFrom($current);
				$fields->addFieldsToTab('Root.WorkflowActions', $wfFields);
			}
		}
	}

	public function onAfterWrite() {
		parent::onAfterWrite();
		
		$workflows = $this->workflowService->getWorkflowsFor($this->owner);
		$rawData = $this->owner->toMap();
		foreach($workflows as $workflow) {
			if($workflow && $this->owner->TransitionID) {
				// we want to transition, so do so if that's a valid transition to take. 
				$action = $workflow->CurrentAction();
				if(!$this->canEditWorkflow($workflow)) {
					continue;
				}

				$allowedFields = $workflow->getWorkflowFields()->saveableFields();
				// Strip the workflow id's from formfields and save them into the Action
				// example: Comment[66] will be $action->Comment
				$allowed = array_keys($allowedFields);
				if(count($allowed)) {
					foreach($allowed as $allowField) {
						$actionFieldName = preg_replace('|\[[^]]*\]|', '', $allowField, -1, $replacementCount);
						if(isset($data[$actionFieldName]) && $actionFieldName != 'TransitionID') {
							if($replacementCount) {
								$action->$actionFieldName = $data[$actionFieldName][$workflow->ID];
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
		}
	}
}