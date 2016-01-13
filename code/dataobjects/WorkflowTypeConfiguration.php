<?php

/**
 * Description of WorkflowTypeConfiguration
 *
 * @author Stephen McMahon <stephen@silverstripe.com.au>
 */
class WorkflowTypeConfiguration extends DataObject {
	
	private static $db = array(
		'ControlledTypes' => 'MultiValueField'
	);

	private static $extensions = array(
		'WorkflowApplicable' => 'WorkflowApplicable'
	);
	
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		
		$classListField = MultiValueDropdownField::create(
			'ControlledTypes',
			'Controlled Types',
			ClassInfo::subclassesFor('DataObject')
		);
		
		$fields->replaceField('ControlledTypes', $classListField);
		
		return $fields;
	}
	
}
