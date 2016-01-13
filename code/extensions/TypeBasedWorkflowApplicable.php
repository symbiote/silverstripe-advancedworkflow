<?php

/**
 * Description of TypeBasedWorkflowApplicable
 *
 * @author Stephen McMahon <stephen@silverstripe.com.au>
 */
class TypeBasedWorkflowApplicable extends Extension {
	
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
		$wftc = null;
		
		foreach ($classAncestry as $className) {
			$workflows = WorkflowTypeConfiguration::get()->filter('ControlledTypesValue:PartialMatch', $className);
			if($workflows->count()) {
				//@TODO add some logic or config here. If multiple Workflows matc which one do we use?
				$wftc = $workflows->last();
				break;
			}
		}
		
		return $wftc;
	}
}
