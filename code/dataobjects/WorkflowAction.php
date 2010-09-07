<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * A workflow action describes a the 'state' a workflow can
 * be in while waiting for 
 *
 * @author marcus@silverstripe.com.au
 */
class WorkflowAction extends DataObject {
    public static $db = array(
		'Title' => 'Varchar(255)',
		'Type' => "Enum('Dynamic,Manual','Manual')",
		'Executed' => 'Boolean',
	);

	public static $has_one = array(
		'WorkflowDef' => 'WorkflowDefinition',
		'Workflow' => 'WorkflowInstance',
	);

	/**
	 * Gets a list of all transitions available in this workflow
	 */
	public function getAllTransitions() {
		return DataObject::get('WorkflowTransition', '"ActionID" = '.((int) $this->ID));
	}


	/**
	 * Perform whatever needs to be done for this action. If this action can be considered executed, then
	 * return true - if not (ie it needs some user input first), return false and 'execute' will be triggered
	 * at a later point in time after the user has provided more data, either directly or indirectly. 
	 *
	 * @return boolean
	 *			Has this action finished? If so, just execute the 'complete' functionality.
	 */
	public function execute() {

		return true;
	}

	/**
	 * Called if the 'executed' property is set to 'true' when the engine next has a chance to analyse
	 * the state of this action.
	 *
	 * If this action has a single valid transition, it should be returned by this method and will be immediately
	 * followed. Otherwise, return the list of transitions that are valid for this action to follow; it is then
	 * up to the user to decide which to follow. 
	 */
	public function completeAction() {
		
	}

}