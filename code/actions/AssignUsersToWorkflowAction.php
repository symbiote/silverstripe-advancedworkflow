<?php
/**
 * A workflow action that allows additional users or groups to be assigned to
 * the workflow part-way through the workflow path.
 *
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    activityworkflow
 * @subpackage actions
 */
class AssignUsersToWorkflowAction extends WorkflowAction {

	public static $many_many = array(
		'Users'  => 'Member',
		'Groups' => 'Group'
	);

	public static $icon = 'activityworkflow/images/assign.png';

	public function execute() {
		$this->Workflow()->Users()->addMany($this->Users());
		$this->Workflow()->Groups()->addMany($this->Groups());

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
			'AssignUsers' => _t('ActivityWorkflow.ASSIGNUSERS', 'Assign Users'),
			'Users'       => _t('ActivityWorkflow.USERS', 'Users'),
			'Groups'      => _t('ActivityWorkflow.GROUPS', 'Groups')
		));
	}

	/**
	 * Copies the users and groups across from the definition action.
	 *
	 * @param WorkflowDefinition $definition
	 */
	public function cloneFromDefinition(WorkflowDefinition $definition) {
		$this->Users()->addMany($definition->Users());
		$this->Groups()->addMany($definition->Groups());
	}

}