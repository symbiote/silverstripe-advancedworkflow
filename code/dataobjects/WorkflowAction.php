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

	public static $icon = 'activityworkflow/images/action.png';

	public static $extensions = array(
		'SortableObject',
	);

	public static $allowed_children = array('WorkflowTransition');

	/**
	 * Gets a list of all transitions available from this workflow action
	 */
	public function getAllTransitions() {
		return DataObject::get('WorkflowTransition', '"ActionID" = '.((int) $this->ID), 'Sort ASC');
	}

	/**
	 * Can documents in the current workflow state be edited?
	 * 
	 */
	public function canEdit() {
		return true;
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
	 */
	public function getNextTransitions() {
		$available = $this->getAllTransitions();
		// iterate through the transitions and see if they're valid for the current state of the item being
		// workflowed
		$valid = new DataObjectSet();
		if ($available) {
			foreach ($available as $t) {
				if ($t->isValid()) {
					$valid->push($t);
				}
			}
		} else {
			// we don't have a valid next transition (at least, for this user...) so just pause here?
			// NOOP - we just want to return the empty $valid set for now. 
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
		$kids = $this->getAllTransitions();
		if ($kids) {
			return $kids;
		}
		return new DataObjectSet();
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