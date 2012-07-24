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
		'Title' 	=> 'Varchar(128)',
		'Sort'  	=> 'Int',
		'Type' 		=> "Enum('Active, Passive', 'Active')"
	);

	public static $default_sort = 'Sort';

	public static $has_one = array(
		'Action' => 'WorkflowAction',
		'NextAction' => 'WorkflowAction',
	);

	public static $many_many = array(
		'Users'  => 'Member',
		'Groups' => 'Group'
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
	public function onBeforeWrite() {
		if(!$this->Sort) {
			$this->Sort = DB::query('SELECT MAX("Sort") + 1 FROM "WorkflowTransition"')->value();
		}

		parent::onBeforeWrite();
	}

	public function validate() {
		$result = parent::validate();

		if ($this->ActionID && ($this->ActionID == $this->NextActionID)) {
			$result->error(_t(
				'WorkflowTransition.TRANSITIONLOOP',
				'A transition cannot lead back to its parent action.'));
		}

		if ($this->ID && (!$this->ActionID || !$this->NextActionID)) {
			$result->error(_t(
				'WorkflowTransition.MUSTSETACTIONS',
				'You must set a parent and transition action.'));
		}

		return $result;
	}

	/* CMS FUNCTIONS */

	public function getCMSFields() {
		$fields = new FieldList(new TabSet('Root'));
		$fields->addFieldToTab('Root.Main', new TextField('Title', _t('WorkflowAction.TITLE', 'Title')));

		$filter = '';

		$reqParent = isset($_REQUEST['ParentID']) ? (int) $_REQUEST['ParentID'] : 0;
        $attachTo = $this->ActionID ? $this->ActionID : $reqParent;

		if ($attachTo) {
            $action = DataObject::get_by_id('WorkflowAction', $attachTo);
            if ($action && $action->ID) {
                $filter = '"WorkflowDefID" = '.((int) $action->WorkflowDefID);
            }
		}

		$actions = DataObject::get('WorkflowAction', $filter);
		$options = array();
		if ($actions) {
			$options = $actions->map();
		}
		
		$typeOptions = $this->dbObject('Type')->enumValues();

		$fields->addFieldToTab('Root.Main', new DropdownField(
			'ActionID',
			_t('WorkflowTransition.ACTION', 'Action'),
			$options));
		$fields->addFieldToTab('Root.Main', new DropdownField(
			'NextActionID',
			_t('WorkflowTransition.NEXT_ACTION', 'Next Action'),
			$options,
			null, null,
			_t('WorkflowTransition.SELECTONE', '(Select one)')));
		$fields->addFieldToTab('Root.Main', new DropdownField(
			'Type',
			_t('WorkflowTransition.TYPE', 'Type'),
			$typeOptions
			));

		$fields->addFieldToTab('Root.RestrictToUsers', new TreeMultiselectField('Users', _t('WorkflowDefinition.USERS', 'Restrict to Users'), 'Member'));
		$fields->addFieldToTab('Root.RestrictToUsers', new TreeMultiselectField('Groups', _t('WorkflowDefinition.GROUPS', 'Restrict to Groups'), 'Group'));

		$this->extend('updateCMSFields', $fields);

		return $fields;
	}

	public function getValidator() {
		return new RequiredFields('Title', 'ActionID', 'NextActionID');
	}

	public function numChildren() {
		return 0;
	}

	public function summaryFields() {
		return array('Title' => 'Title');
	}


	/**
	 * Check if the current user can execute this transition
	 *
	 * @return bool
	 **/
	public function canExecute(WorkflowInstance $workflow){
		$return = true; 

		$members = $this->getAssignedMembers();

		// check if the member is in the list of assigned members
		if($members->exists()){
			if(!$members->find('ID', Member::currentUserID())){
				$return = false;
			}
		}

		if($return){
			$return = $this->extend('extendCanExecute', $workflow);	
			if(is_array($return)) $return = $return[0]; // @todo work out why this is returning an array...
		}
		
		if($return !== false){
			return true;
		}else{
			return $return;
		}


	}

	/**
	 * Returns a set of all Members that are assigned to this transition, either directly or via a group.
	 *
	 * @return DataObjectSet
	 */
	public function getAssignedMembers() {
		$members = $this->Users();
		$groups  = $this->Groups();

		foreach($groups as $group) {
			$members->merge($group->Members());
		}

		$members->removeDuplicates();
		return $members;
	}

}