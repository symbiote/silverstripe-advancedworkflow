<?php
/**
 * This class is a {@link GridField} component that adds an export action for 
 * WorkflowDefinition objects shown in GridFields.
 *
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class GridFieldExportAction implements GridField_ColumnProvider, GridField_ActionProvider {
	
	/**
	 * Add a column 'Delete'
	 * 
	 * @param type $gridField
	 * @param array $columns 
	 */
	public function augmentColumns($gridField, &$columns) {
		if(!in_array('Actions', $columns)) {
			$columns[] = 'Actions';
		}
	}
	
	/**
	 * Return any special attributes that will be used for FormField::create_tag()
	 *
	 * @param GridField $gridField
	 * @param DataObject $record
	 * @param string $columnName
	 * @return array
	 */
	public function getColumnAttributes($gridField, $record, $columnName) {
		return array('class' => 'col-buttons');
	}
	
	/**
	 * Add the title 
	 * 
	 * @param GridField $gridField
	 * @param string $columnName
	 * @return array
	 */
	public function getColumnMetadata($gridField, $columnName) {
		if($columnName == 'Actions') {
			return array('title' => '');
		}
	}
	
	/**
	 * Which columns are handled by this component
	 * 
	 * @param type $gridField
	 * @return type 
	 */
	public function getColumnsHandled($gridField) {
		return array('Actions');
	}
	
	/**
	 * Which GridField actions are this component handling
	 *
	 * @param GridField $gridField
	 * @return array 
	 */
	public function getActions($gridField) {
		return array('exportrecord');
	}
	
	/**
	 *
	 * @param GridField $gridField
	 * @param DataObject $record
	 * @param string $columnName
	 * @return string - the HTML for the column 
	 */
	public function getColumnContent($gridField, $record, $columnName) {
		// Disable the export icon if current user doesn't have access to view CMS Security settings
		if(!Permission::check('CMS_ACCESS_SecurityAdmin')) {
			return '';
		}
		
		$field = GridField_FormAction::create($gridField,  'ExportRecord'.$record->ID, false, "exportrecord",
				array('RecordID' => $record->ID))
			->addExtraClass('gridfield-button-export')
			->setAttribute('title', _t('GridAction.Export', "Export"))
			->setAttribute('data-icon', 'export')
			->setDescription(_t('GridAction.EXPORT_DESCRIPTION','Export'));
		
		$segment1 = Director::baseURL();
		$segment2 = AdvancedWorkflowAdmin::$url_segment;
		$segment3 = $record->getClassName();
		$fields = new ArrayData(array(
			'Link' => Controller::join_links($segment1, 'admin', $segment2 , $segment3, 'export', $record->ID)
		));
		
		return $field->Field()->renderWith('GridField_ExportAction', $fields);
	}
	
	/**
	 * Handle the actions and apply any changes to the GridField
	 *
	 * @param GridField $gridField
	 * @param string $actionName
	 * @param mixed $arguments
	 * @param array $data - form data
	 * @return void
	 */
	public function handleAction(GridField $gridField, $actionName, $arguments, $data) {
	}
}
