<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * A workflow transition.
 *
 * When used within the context of a workflow, the transition will have its
 * "isValid()" method call. This must return true or false to indicate whether
 * this transition is valid for the state of the workflow that it a part of.
 *
 * Therefore, any logic around whether the workflow can proceed should be
 * managed within this method. 
 *
 * @author marcus@silverstripe.com.au
 */
class WorkflowTransition extends DataObject {
    public static $db = array(
		'Title' => 'Varchar(128)',
	);

	public static $has_one = array(
		'Action' => 'WorkflowAction',
		'NextAction' => 'WorkflowAction',
	);

	/**
	 * Is it valid for this transition to be followed given the
	 * state of the current workflow? 
	 */
	public function isValid() {
		return true;
	}

	public function summaryFields() {
		return array('Title' => 'Title');
	}

	public function getTableFieldTypes() {
		$fields = array(
			'Title' => 'TextField',
		);
		
		return $fields;
	}
}