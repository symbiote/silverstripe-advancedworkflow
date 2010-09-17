<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * A workflow action that's used to change the users that are assigned to the
 * running instance of a workflow
 *
 * @author marcus@silverstripe.com.au
 */
class AssignUsersToWorkflowAction extends WorkflowAction {

	public static $icon = 'activityworkflow/images/assign.png';
	
	/**
	 * @var array
	 */
	public static $many_many = array(
		'Users' => 'Member',
		'Groups' => 'Group'
	);

    public function execute() {
		// update the list of assigned users based on what was set in the definition.

		// and just return true
		return true;
	}
}