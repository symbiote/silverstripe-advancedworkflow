<?php
/**
 *
 * @package advancedworkflow
 */
class GridFieldWorkflowRestrictedEditButton implements GridField_ColumnProvider {
	
	/**
	 * Add a column
	 * 
	 * @param type $gridField
	 * @param array $columns 
	 */
	public function augmentColumns($gridField, &$columns) {
		if(!in_array('Actions', $columns))
			$columns[] = 'Actions';
	}
	
	/**
	 * Append a 'disabled' CSS class to GridField rows whose WorkflowInstance records are not viewable/editable
	 * by the current user. 
	 * 
	 * This is used to visually "grey out" records and it's leveraged in some overriding JavaScript, to maintain an ability
	 * to click the target object's hyperlink.
	 *
	 * @param GridField $gridField
	 * @param DataObject $record
	 * @param string $columnName
	 * @return array
	 */
	public function getColumnAttributes($gridField, $record, $columnName) {
		$defaultAtts = array('class' => 'col-buttons');
		if($record instanceof WorkflowInstance) {
			if(!$record->getAssignedMembers()->find('ID', Member::currentUserID())) {
				$atts['class'] = $defaultAtts['class'].' disabled';
				return $atts;
			}
			return $defaultAtts;
		}
		return $defaultAtts;
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
	 * @param GridField $gridField
	 * @param DataObject $record
	 * @param string $columnName
	 *
	 * @return string - the HTML for the column 
	 */
	public function getColumnContent($gridField, $record, $columnName) {
		$data = new ArrayData(array(
			'Link' => Controller::join_links($gridField->Link('item'), $record->ID, 'edit')
		));
		return $data->renderWith('GridFieldEditButton');
	}
}
