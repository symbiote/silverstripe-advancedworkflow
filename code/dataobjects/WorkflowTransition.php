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
	 *
	 * @var array $extendedMethodReturn A basic extended validation routine method return format
	 */
	public static $extendedMethodReturn = array(
		'fieldName'	=>null,
		'fieldField'=>null,
		'fieldMsg'	=>null,
		'fieldValid'=>true
	);

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
		return $result;
	}

	/* CMS FUNCTIONS */

	public function getCMSFields() {
		$fields = new FieldList(new TabSet('Root'));
		$fields->addFieldToTab('Root.Main', new TextField('Title', $this->fieldLabel('Title')));

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

		$defaultAction = $action?$action->ID:"";
		
		$typeOptions = array(
			'Active' => _t('WorkflowTransition.Active', 'Active'),
			'Passive' => _t('WorkflowTransition.Passive', 'Passive'),
		);

		$fields->addFieldToTab('Root.Main', new DropdownField(
			'ActionID',
			$this->fieldLabel('ActionID'),
			$options, $defaultAction));
		$fields->addFieldToTab('Root.Main', $nextActionDropdownField = new DropdownField(
			'NextActionID',
			$this->fieldLabel('NextActionID'),
			$options));
		$nextActionDropdownField->setEmptyString(_t('WorkflowTransition.SELECTONE', '(Select one)'));
		$fields->addFieldToTab('Root.Main', new DropdownField(
			'Type',
			_t('WorkflowTransition.TYPE', 'Type'),
			$typeOptions
		));

		$members = Member::get();
		$fields->findOrMakeTab(
			'Root.RestrictToUsers',
			_t('WorkflowTransition.TabTitle', 'Restrict to users')
		);
		$fields->addFieldToTab('Root.RestrictToUsers', new CheckboxSetField('Users', _t('WorkflowDefinition.USERS', 'Restrict to Users'), $members));
		$fields->addFieldToTab('Root.RestrictToUsers', new TreeMultiselectField('Groups', _t('WorkflowDefinition.GROUPS', 'Restrict to Groups'), 'Group'));

		$this->extend('updateCMSFields', $fields);

		return $fields;
	}

	public function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);
		$labels['Title'] = _t('WorkflowAction.TITLE', 'Title');
		$labels['ActionID'] = _t('WorkflowTransition.ACTION', 'Action');
		$labels['NextActionID'] = _t('WorkflowTransition.NEXT_ACTION', 'Next Action');

		return $labels;
	}

	public function getValidator() {
		$required = new AWRequiredFields('Title', 'ActionID', 'NextActionID');
		$required->setCaller($this);
		return $required;
	}

	public function numChildren() {
		return 0;
	}

	public function summaryFields() {
		return array(
			'Title' => $this->fieldLabel('Title')
		);
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
	 * Allows users who have permission to create a WorkflowDefinition, to create actions on it too.
	 * 
	 * @param  Member $member
	 * @return bool
	 */
	public function canCreate($member = null) {
		return $this->Action()->WorkflowDef()->canCreate($member);
	}
	
	/**
	 * @param  Member $member
	 * @return bool
	 */	
	public function canEdit($member = null) {
		return $this->canCreate($member);
	}
	
	/**
	 * @param  Member $member
	 * @return bool
	 */		
	public function canDelete($member = null) {
		return $this->canCreate($member);
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

	/*
	 * A simple field same-value checker.
	 *
	 * @param array $data
	 * @return array
	 * @see {@link AWRequiredFields}
	 */
	public function extendedRequiredFieldsNotSame($data=null) {
		$check = array('ActionID','NextActionID');
		foreach($check as $fieldName) {
			if(!isset($data[$fieldName])) {
				return self::$extendedMethodReturn;
			}
		}
		// Have we found some identical values?
		if($data[$check[0]] == $data[$check[1]]) {
			self::$extendedMethodReturn['fieldName'] = $check[0]; // Used to display to the user, so the first of the array is fine
			self::$extendedMethodReturn['fieldValid'] = false;
			self::$extendedMethodReturn['fieldMsg'] = _t(
				'WorkflowTransition.TRANSITIONLOOP',
				'A transition cannot lead back to its parent action.');
		}
		return self::$extendedMethodReturn;
	}
}