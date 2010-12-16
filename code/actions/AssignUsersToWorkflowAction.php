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

	public static $many_many = array(
		'Users'  => 'Member',
		'Groups' => 'Group'
	);

	public static $icon = 'advancedworkflow/images/assign.png';

	public function execute(WorkflowInstance $workflow) {
		$workflow->Users()->removeAll();
		$workflow->Groups()->removeAll();
		$workflow->Users()->addMany($this->Users());
		$workflow->Groups()->addMany($this->Groups());
		return true;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->addFieldsToTab('Root.Main', array(
			new HeaderField('AssignUsers', $this->fieldLabel('AssignUsers')),
			new TreeMultiselectField('Users', $this->fieldLabel('Users'), 'Member'),
			new TreeMultiselectField('Groups', $this->fieldLabel('Groups'), 'Group')
		));

		return $fields;
	}

	public function fieldLabels() {
		return array_merge(parent::fieldLabels(), array(
			'AssignUsers' => _t('AssignUsersToWorkflowAction.ASSIGNUSERS', 'Assign Users'),
			'Users'       => _t('AssignUsersToWorkflowAction.USERS', 'Users'),
			'Groups'      => _t('AssignUsersToWorkflowAction.GROUPS', 'Groups')
		));
	}

}