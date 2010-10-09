<?php
/**
 * A workflow action describes a the 'state' a workflow can be in, and
 * the action(s) that occur while in that state. An action can then have
 * subsequent transitions out of the current state. 
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package activityworkflow
 */
class WorkflowAction extends DataObject {

	public static $db = array(
		'Title'    => 'Varchar(255)',
		'Comment'  => 'Text',
		'Type'     => "Enum('Dynamic,Manual','Manual')",
		'Executed' => 'Boolean',
		'Sort'     => 'Int'
	);

	public static $default_sort = 'Sort';

	public static $has_one = array(
		'WorkflowDef' => 'WorkflowDefinition',
		'Member'      => 'Member'
	);

	public static $has_many = array(
		'Transitions' => 'WorkflowTransition.Action'
	);

	public static $icon = 'activityworkflow/images/action.png';

	/**
	 * Can documents in the current workflow state be edited?
	 *
	 * @param  DataObject $target
	 * @return bool
	 */
	public function canEditTarget(DataObject $target) {
		return false;
	}

	/**
	 * Does this action restrict viewing of the document?
	 *
	 * @param  DataObject $target
	 * @return bool
	 */
	public function canViewTarget(DataObject $target) {
		return true;
	}

	/**
	 * Does this action restrict the publishing of a document?
	 *
	 * @param  DataObject $target
	 * @return bool
	 */
	public function canPublishTarget(DataObject $target) {
		return false;
	}

	/**
	 * Gets an object that is used for saving the actual state of things during
	 * a running workflow. It still uses the workflow action def for managing the
	 * functional execution, however if you need to store additional data for
	 * the state, you can specify your own instance instead. 
	 *
	 * @return WorkflowActionInstance
	 */
	public function getInstanceForWorkflow() {
		$instance = new WorkflowActionInstance();
		$instance->BaseActionID = $this->ID;
		return $instance;
	}

	/**
	 * Perform whatever needs to be done for this action. If this action can be considered executed, then
	 * return true - if not (ie it needs some user input first), return false and 'execute' will be triggered
	 * again at a later point in time after the user has provided more data, either directly or indirectly.
	 *
	 * @param  WorkflowInstance $workflow
	 * @return bool Returns true if this action has finished.
	 */
	public function execute(WorkflowInstance $workflow) {
		return true;
	}

	public function onBeforeWrite() {
		if(!$this->Sort) {
			$this->Sort = DB::query('SELECT MAX("Sort") + 1 FROM "WorkflowAction"')->value();
		}

		parent::onBeforeWrite();
	}

	/* CMS RELATED FUNCTIONALITY... */

	/**
	 * Gets fields for when this is part of an active workflow
	 */
	public function updateWorkflowFields($fields) {
		$fields->push(new TextareaField('Comment', _t('WorkflowAction.COMMENT', 'Comment')));
	}

	public function numChildren() {
		return count($this->Transitions());
	}

	public function getCMSFields() {
		$fields = new FieldSet(new TabSet('Root'));
		$fields->addFieldToTab('Root.Main', new TextField('Title', _t('WorkflowAction.TITLE', 'Title')));

		return $fields;
	}
	
	public function summaryFields() {
		return array('Title' => 'Title', 'Transitions' => 'Transitions');
	}

}