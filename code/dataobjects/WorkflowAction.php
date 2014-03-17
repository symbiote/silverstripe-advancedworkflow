<?php
/**
 * A workflow action describes a the 'state' a workflow can be in, and
 * the action(s) that occur while in that state. An action can then have
 * subsequent transitions out of the current state. 
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowAction extends DataObject {

	public static $db = array(
		'Title'				=> 'Varchar(255)',
		'Comment'			=> 'Text',
		'Type'				=> "Enum('Dynamic,Manual','Manual')",  // is this used?
		'Executed'			=> 'Boolean',
		'AllowEditing'		=> "Enum('By Assignees,Content Settings,No','No')",		// can this item be edited?
		'Sort'				=> 'Int',
		'AllowCommenting'	=> 'Boolean'
	);

	public static $defaults = array(
		'AllowCommenting'	=> '1',
	);
	
	public static $default_sort = 'Sort';

	public static $has_one = array(
		'WorkflowDef' => 'WorkflowDefinition',
		'Member'      => 'Member'
	);

	public static $has_many = array(
		'Transitions' => 'WorkflowTransition.Action'
	);

	/**
	 * The type of class to use for instances of this workflow action that are used for storing the 
	 * data of the instance. 
	 *
	 * @var string
	 */
	public static $instance_class = 'WorkflowActionInstance';
	
	public static $icon = 'advancedworkflow/images/action.png';

	/**
	 * Can documents in the current workflow state be edited?
	 * 
	 * Only return true or false if this is an absolute value; the WorkflowActionInstance
	 * will try and figure out an appropriate value for the actively running workflow
	 * if null is returned from this method. 
	 *
	 * @param  DataObject $target
	 * @return bool
	 */
	public function canEditTarget(DataObject $target) {
		return null;
	}

	/**
	 * Does this action restrict viewing of the document?
	 *
	 * @param  DataObject $target
	 * @return bool
	 */
	public function canViewTarget(DataObject $target) {
		return null;
	}

	/**
	 * Does this action restrict the publishing of a document?
	 *
	 * @param  DataObject $target
	 * @return bool
	 */
	public function canPublishTarget(DataObject $target) {
		return null;
	}
	
	/**
	 * Allows users who have permission to create a WorkflowDefinition, to create actions on it too.
	 * 
	 * @param  Member $member
	 * @return bool
	 */
	public function canCreate($member = null) {
		return $this->WorkflowDef()->canCreate($member);
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
	
	/*
	 * If there is only a single action defined for a workflow, there's no sense
	 * in allowing users to add a transition to it (and causing errors).
	 * Hide the "Add Transition" button in this case
	 *
	 * @return boolean true if we should disable the button, false otherwise
	 */
	public function canAddTransition() {
		return ($this->WorkflowDef()->numChildren() >1);
	}	

	/**
	 * Gets an object that is used for saving the actual state of things during
	 * a running workflow. It still uses the workflow action def for managing the
	 * functional execution, however if you need to store additional data for
	 * the state, you can specify your own WorkflowActionInstance instead of
	 * the default to capture these elements
	 *
	 * @return WorkflowActionInstance
	 */
	public function getInstanceForWorkflow() {
		$instanceClass = $this->stat('instance_class');
		$instance = new $instanceClass();
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
	
	/**
	 * When deleting an action from a workflow definition, make sure that workflows currently paused on that action
	 * are deleted
	 * Also removes all outbound transitions
	 */
	public function onAfterDelete() {
		parent::onAfterDelete();
		$wfActionInstances = WorkflowActionInstance::get()
				->leftJoin("WorkflowInstance",'"WorkflowInstance"."ID" = "WorkflowActionInstance"."WorkflowID"')
				->where(sprintf('"BaseActionID" = %d AND ("WorkflowStatus" IN (\'Active\',\'Paused\'))', $this->ID));
		foreach ($wfActionInstances as $wfActionInstance){
			$wfInstances = WorkflowInstance::get()->filter('CurrentActionID', $wfActionInstance->ID);
			foreach ($wfInstances as $wfInstance){
				$wfInstance->Groups()->removeAll();
				$wfInstance->Users()->removeAll();
				$wfInstance->delete();
			}
			$wfActionInstance->delete();
		}
		// Delete outbound transitions
		$transitions = WorkflowTransition::get()->filter('ActionID', $this->ID);
		foreach ($transitions as $transition){
			$transition->Groups()->removeAll();
			$transition->Users()->removeAll();
			$transition->delete();
		}
	}
	
	/**
	 * Called when the current target of the workflow has been updated
	 */
	public function targetUpdated(WorkflowInstance $workflow) {
	}

	/* CMS RELATED FUNCTIONALITY... */


	public function numChildren() {
		return count($this->Transitions());
	}

	public function getCMSFields() {
		
		$fields = new FieldList(new TabSet('Root'));
		$typeLabel = _t('WorkflowAction.CLASS_LABEL', 'Action Class');
		$fields->addFieldToTab('Root.Main', new ReadOnlyField('WorkflowActionClass', $typeLabel, $this->singular_name()));
		$titleField = new TextField('Title', $this->fieldLabel('Title'));
		$titleField->setDescription(_t(
			'WorkflowAction.TitleDescription',
			'The Title is used as the button label for this Workflow Action'
		));
		$fields->addFieldToTab('Root.Main', $titleField);
		$fields->addFieldToTab('Root.Main', new DropdownField('AllowEditing', $this->fieldLabel('AllowEditing'), 
			array(
				'By Assignees' => _t('AllowEditing.ByAssignees', 'By Assignees'),
				'Content Settings' => _t('AllowEditing.ContentSettings', 'Content Settings'),
				'No' => _t('AllowEditing.NoString', 'No')
			),
		 	_t('AllowEditing.NoString', 'No')
		));
		$fields->addFieldToTab('Root.Main', new CheckboxField('AllowCommenting', $this->fieldLabel('AllowCommenting'),$this->AllowCommenting));
		
		return $fields;
	}

	public function getValidator() {
		return new RequiredFields('Title');
	}

	public function summaryFields() {
		return array(
			'Title' => $this->fieldLabel('Title'), 
			'Transitions' => $this->fieldLabel('Transitions'),
		);
	}

	public function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);
		$labels['Comment'] = _t('WorkflowAction.CommentLabel', 'Comment');
		$labels['Type'] = _t('WorkflowAction.TypeLabel', 'Type');
		$labels['Executed'] = _t('WorkflowAction.ExecutedLabel', 'Executed');
		$labels['AllowEditing'] = _t('WorkflowAction.ALLOW_EDITING', 'Allow editing during this step?');
		$labels['Title'] = _t('WorkflowAction.TITLE', 'Title');
		$labels['AllowCommenting'] = _t('WorkflowAction.ALLOW_COMMENTING','Allow Commenting?');
		$labels['Transitions'] = _t('WorkflowAction.Transitions','Transitions');

		return $labels;
	}
	
	/**
	 * Used for Front End Workflows
	 */
	public function updateFrontendWorkflowFields($fields, $workflow){	
		
	}	
	

	public function Icon() {
		return $this->stat('icon');
	}
}