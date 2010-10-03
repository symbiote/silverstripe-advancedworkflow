<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * A workflow action describes a the 'state' a workflow can be in, and
 * the action(s) that occur while in that state. An action can then have
 * subsequent transitions out of the current state. 
 *
 * @author marcus@silverstripe.com.au
 */
class WorkflowAction extends DataObject {
    public static $db = array(
		'Title' => 'Varchar(255)',
		'Comment' => 'HTMLText',
		'Type' => "Enum('Dynamic,Manual','Manual')",
		'Executed' => 'Boolean',
	);

	public static $has_one = array(
		'WorkflowDef' => 'WorkflowDefinition',
		'Workflow' => 'WorkflowInstance',
	);

	public static $has_many = array(
		'Transitions' => 'WorkflowTransition.Action'
	);

	public static $icon = 'activityworkflow/images/action.png';

	public static $extensions = array(
		'SortableObject',
	);

	public static $allowed_children = array('WorkflowTransition');

	/**
	 * Returns an array of possible action classes to action title, suitable for use in a dropdown.
	 *
	 * @return array
	 */
	public static function get_dropdown_map() {
		$classes = ClassInfo::subclassesFor(__CLASS__);
		$actions = array();

		array_shift($classes);
		foreach($classes as $class) {
			$actions[$class] = singleton($class)->getActionTitle();
		}

		return $actions;
	}

	/**
	 * Returns the action title that describes all instances of this action - default to the singular name.
	 *
	 * @return string
	 */
	public function getActionTitle() {
		return $this->singular_name();
	}

	/**
	 * Can documents in the current workflow state be edited?
	 */
	public function canEdit() {
		return false;
	}

	/**
	 * Does this action restrict viewing of the document?
	 *
	 * @return boolean
	 */
	public function canView() {
		return true;
	}

	/**
	 * Does this action restrict the publishing of a document?
	 *
	 * @return boolean
	 */
	public function canPublish() {
		return false;
	}

	/**
	 * Perform whatever needs to be done for this action. If this action can be considered executed, then
	 * return true - if not (ie it needs some user input first), return false and 'execute' will be triggered
	 * again at a later point in time after the user has provided more data, either directly or indirectly.
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
	 *
	 * @return DataObjectSet
	 */
	public function getValidTransitions() {
		$available = $this->Transitions();
		$valid     = new DataObjectSet();

		// iterate through the transitions and see if they're valid for the current state of the item being
		// workflowed
		if($available) foreach($available as $transition) {
			if($transition->isValid()) $valid->push($transition);
		}

		return $valid;
	}


	/**
	 * Called when this workflow action is cloned from the definition of the action
	 *
	 * If your custom action defines custom properties, this is where you can update
	 * them for the new definition
	 *
	 * @param WorkflowTransition $action
	 */
	public function cloneFromDefinition(WorkflowAction $action) {

	}

	/* CMS RELATED FUNCTIONALITY... */

	/**
	 * Gets fields for when this is part of an active workflow
	 */
	public function updateWorkflowFields($fields) {
		$fields->push(new TextareaField('Comment', _t('WorkflowAction.COMMENT', 'Comment')));
	}

	public function numchildren() {
		return $this->stageChildren()->Count();
	}

	public function stageChildren() {
		return ($children = $this->Transitions()) ? $children : new DataObjectSet();
	}

	public function RelativeLink() {
		return '';
	}
	
	public function getCMSFields() {
		$fields = new FieldSet(new TabSet('Root'));
		$fields->addFieldToTab('Root.Main', new TextField('Title', _t('WorkflowAction.TITLE', 'Title')));

		return $fields;
	}
	
	public function summaryFields() {
		return array('Title' => 'Title', 'Transitions' => 'Transitions');
	}

	public function getTableFieldTypes() {
		$fields = array(
			'Title' => 'TextField',
			'Transitions' => new LiteralField('Transition', 'Can only edit transitions once created'),
		);

		if ($this->ID) {
		}

		return $fields;
	}
}