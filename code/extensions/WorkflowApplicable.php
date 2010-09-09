<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * DataObjects that have the WorkflowApplicable extension can have a
 * workflow definition applied to them. At some point, the workflow definition is then
 * triggered. 
 *
 * @author marcus@silverstripe.com.au
 */
class WorkflowApplicable extends DataObjectDecorator {
	public function extraStatics() {
		return array(
			'has_one' => array(
				'WorkflowDefinition' => 'WorkflowDefinition',
				'ActiveWorkflow' => 'WorkflowInstance',
			)
		);
	}

	public function updateCMSFields(FieldSet $fields) {
		
	}
}