<?php

/**
 * Description of TypeBasedWorkflowApplicable
 *
 * @author Stephen McMahon <stephen@silverstripe.com.au>
 */
class TypeBasedWorkflowApplicable extends Extension {
	
	protected $workflowTypeApplicable = null;

	public function updateCMSFields(FieldList $fields) {
		$fields->removeByName('WorkflowDefinitionID');
		$fields->removeByName('AdditionalWorkflowDefinitions');
	}
	
	/**
	 * Looks for any WorkflowTypeConfiguration that applies to this Class or it's Ancestors
	 * 
	 * @return WorkflowTypeConfiguration|null
	 */
	public function workflowParent() {
		
		//DataObject::getClassAncestry returns with the current class as the last element so we flip it before we loop
		$classAncestry = array_reverse($this->owner->getClassAncestry());

		foreach ($classAncestry as $className) {
			$workflows = WorkflowTypeConfiguration::get()->filter('ControlledTypesValue:PartialMatch', $className);
			if($workflows->count()) {
				//@TODO add some logic or config here. If multiple Workflows matc which one do we use?
				if($this->workflowTypeApplicable === null) {
					$this->workflowTypeApplicable = $workflows->first();
				}
				//add any addtional workflows into the stack
				$this->addAdditionalWorkflowDefinitions($workflows);
			}
		}

		return $this->workflowTypeApplicable;
	}

	public function addAdditionalWorkflowDefinitions($workflows) {
		foreach($workflows as $workflow) {
			if($this->workflowTypeApplicable->ID !== $workflow->ID) {
				$this->owner->AdditionalWorkflowDefinitions()->add($workflow->WorkflowDefinition());
			}
			if($workflow->AdditionalWorkflowDefinitions()->count()) {
				$this->owner->AdditionalWorkflowDefinitions()->addMany(
					$workflow->AdditionalWorkflowDefinitions()->toArray()
				);
			}
		}
	}
}
