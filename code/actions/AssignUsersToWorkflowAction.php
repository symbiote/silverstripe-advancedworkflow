<?php
/**
 * A workflow action that allows additional users or groups to be assigned to
 * the workflow part-way through the workflow path.
 *
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage actions
 */
class AssignUsersToWorkflowAction extends WorkflowAction {

	public static $db = array(
		'AssignInitiator'		=> 'Boolean',
	);

	public static $many_many = array(
		'Users'  => 'Member',
		'Groups' => 'Group'
	);

	public static $icon = 'advancedworkflow/images/assign.png';

	public function execute(WorkflowInstance $workflow) {
		$workflow->Users()->removeAll();
		//Due to http://open.silverstripe.org/ticket/8258, there are errors occuring if Group has been extended
		//We use a direct delete query here before ticket 8258 fixed
		//$workflow->Groups()->removeAll();
		$workflowID = $workflow->ID;
		$query = <<<SQL
		DELETE FROM "WorkflowInstance_Groups" WHERE ("WorkflowInstance_Groups"."WorkflowInstanceID" = '$workflowID');
SQL;
		DB::query($query);
		$workflow->Users()->addMany($this->Users());
		$workflow->Groups()->addMany($this->Groups());
		if ($this->AssignInitiator) {
			$workflow->Users()->add($workflow->Initiator());
		}
		return true;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		
		$cmsUsers = Member::mapInCMSGroups();
		
		$fields->addFieldsToTab('Root.Main', array(
			new HeaderField('AssignUsers', $this->fieldLabel('AssignUsers')),
			new CheckboxField('AssignInitiator', $this->fieldLabel('AssignInitiator')),
			new CheckboxSetField('Users', $this->fieldLabel('Users'), $cmsUsers),
			new TreeMultiselectField('Groups', $this->fieldLabel('Groups'), 'Group')
		));

		return $fields;
	}

	public function fieldLabels($relations = true) {
		return array_merge(parent::fieldLabels($relations), array(
			'AssignUsers'		=> _t('AssignUsersToWorkflowAction.ASSIGNUSERS', 'Assign Users'),
			'Users'				=> _t('AssignUsersToWorkflowAction.USERS', 'Users'),
			'Groups'			=> _t('AssignUsersToWorkflowAction.GROUPS', 'Groups'),
			'AssignInitiator'	=> _t('AssignUsersToWorkflowAction.INITIATOR', 'Assign Initiator'),
		));
	}
}