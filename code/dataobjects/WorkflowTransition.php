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

	public static $extensions = array(
		'SortableObject',
	);

	public static $icon = 'activityworkflow/images/transition.png';

	/**
	 * Is it valid for this transition to be followed given the
	 * state of the current workflow? 
	 */
	public function isValid() {
		return true;
	}

	/**
	 * Before saving, make sure we're not in an infinite loop
	 */
	public function  onBeforeWrite() {
		parent::onBeforeWrite();
		if ($this->ActionID == $this->NextActionID) {
			$this->NextActionID = 0;
		}
	}

	/**
	 * Called when this workflow transition is cloned from the definition of the transition
	 *
	 * If your custom action defines custom properties, this is where you can update
	 * them for the new definition
	 *
	 * @param WorkflowTransition $action
	 */
	public function cloneFromDefinition(WorkflowTransition $action) {

	}

	/* CMS FUNCTIONS */

	public function getCMSFields() {
		$fields = new FieldSet(new TabSet('Root'));
		$fields->addFieldToTab('Root.Main', new TextField('Title', _t('WorkflowAction.TITLE', 'Title')));

		$filter = '';
		if ($this->ActionID) {
			$filter = '"WorkflowDefID" = '.((int) $this->Action()->WorkflowDefID);
		}

		$actions = DataObject::get('WorkflowAction', $filter);
		$options = array();
		if ($actions) {
			$options = $actions->map();
		}

		$fields->addFieldToTab('Root.Main', new DropdownField('ActionID', _t('WorkflowTransition.ACTION', 'Action'), $options));
		$fields->addFieldToTab('Root.Main', new DropdownField('NextActionID', _t('WorkflowTransition.NEXT_ACTION', 'Next Action'), $options));

		return $fields;
	}

	public function numchildren() {
		return $this->stageChildren()->Count();
	}

	public function stageChildren() {
		return new DataObjectSet();
	}

	public function RelativeLink() {
		return '';
	}
	

	public function summaryFields() {
		return array('Title' => 'Title');
	}

}