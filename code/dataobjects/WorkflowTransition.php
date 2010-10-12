<?php
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
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowTransition extends DataObject {

	public static $db = array(
		'Title' => 'Varchar(128)',
		'Sort'  => 'Int'
	);

	public static $default_sort = 'Sort';

	public static $has_one = array(
		'Action' => 'WorkflowAction',
		'NextAction' => 'WorkflowAction',
	);

	public static $icon = 'advancedworkflow/images/transition.png';

	/**
	 * Returns true if it is valid for this transition to be followed given the
	 * current state of a workflow.
	 *
	 * @param  WorkflowInstance $workflow
	 * @return bool
	 */
	public function isValid(WorkflowInstance $workflow) {
		return true;
	}

	/**
	 * Before saving, make sure we're not in an infinite loop
	 */
	public function  onBeforeWrite() {
		if($this->ActionID == $this->NextActionID) {
			$this->NextActionID = 0;
		}

		if(!$this->Sort) {
			$this->Sort = DB::query('SELECT MAX("Sort") + 1 FROM "WorkflowTransition"')->value();
		}

		parent::onBeforeWrite();
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

	public function numChildren() {
		return 0;
	}

	public function summaryFields() {
		return array('Title' => 'Title');
	}

}