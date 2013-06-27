<?php
/**
 * A WorkflowInstance is created whenever a user 'starts' a workflow. 
 * 
 * This 'start' is triggered automatically when the user clicks the relevant 
 * button (eg 'apply for approval'). This creates a standalone object
 * that maintains the state of the workflow process. 
 * 
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowInstance extends DataObject {
    public static $db = array(
		'Title'				=> 'Varchar(128)',
		'WorkflowStatus'	=> "Enum('Active,Paused,Complete,Cancelled','Active')",
		'TargetClass'		=> 'Varchar(64)',
		'TargetID'			=> 'Int',
	);

	public static $has_one = array(
		'Definition'    => 'WorkflowDefinition',
		'CurrentAction' => 'WorkflowActionInstance',
		'Initiator'		=> 'Member',
	);

	public static $has_many = array(
		'Actions'		=> 'WorkflowActionInstance',
	);

	/**
	 * The list of users who are responsible for performing the current WorkflowAction
	 *
	 * @var array
	 */
	public static $many_many = array(
		'Users'			=> 'Member',
		'Groups'		=> 'Group'
	);

	public static $summary_fields = array(
		'Title',
		'WorkflowStatus',
		'Created'
	);
	
	/**
	 * Get the CMS view of the instance. This is used to display the log of 
	 * this workflow, and options to reassign if the workflow hasn't been 
	 * finished yet
	 * 
	 * @return \FieldList 
	 */
	public function getCMSFields() {
		$fields = new FieldList();
		
		if (Permission::check('REASSIGN_ACTIVE_WORKFLOWS')) {
			if ($this->WorkflowStatus == 'Paused' || $this->WorkflowStatus == 'Active') {
				$cmsUsers = Member::mapInCMSGroups();

				$fields->push(new HiddenField('DirectUpdate', '', 1));

				$fields->push(new HeaderField('InstanceReassignHeader',_t('WorkflowInstance.REASSIGN_HEADER', 'Reassign workflow')));
				$fields->push(new CheckboxSetField('Users', _t('WorkflowDefinition.USERS', 'Users'), $cmsUsers));
				$fields->push(new TreeMultiselectField('Groups', _t('WorkflowDefinition.GROUPS', 'Groups'), 'Group'));

			}
		}
		
		if ($this->canEdit()) {
			$action = $this->CurrentAction();
			if ($action->exists()) {
				$actionFields = $this->getWorkflowFields();
				$fields->merge($actionFields);
			}
		}

		$items = WorkflowActionInstance::get()->filter(array(
			'Finished'		=> 1,
			'WorkflowID'	=> $this->ID
		));

		$grid = new GridField('Actions', 'Log', $items);

		$fields->push($grid);

		return $fields;
	}
	
	/**
	 * See if we've been saved in context of managing the workflow directly
	 */
	public function onBeforeWrite() {
		parent::onBeforeWrite();
		
		$vars = $this->record;

		if (isset($vars['DirectUpdate'])) {
			// Unset now so that we don't end up in an infinite loop!
			unset($this->record['DirectUpdate']);
			$this->updateWorkflow($vars);
		}
	}
	
	/**
	 * Update the current state of the workflow
	 * 
	 * Typically, this is triggered by someone modifiying the workflow instance via the modeladmin form
	 * side of things when administering things, such as re-assigning or manually approving a stuck workflow
	 * 
	 * Note that this is VERY similar to AdvancedWorkflowExtension::updateworkflow
	 * but without the formy bits. These two implementations should PROBABLY
	 * be merged
	 * 
	 * @todo refactor with AdvancedWorkflowExtension
	 *
	 * @param type $data
	 * @return 
	 */
	public function updateWorkflow($data) {
		$action = $this->CurrentAction();

		if (!$this->getTarget() || !$this->getTarget()->canEditWorkflow()) {
			return;
		}

		$allowedFields = $this->getWorkflowFields()->saveableFields();
		unset($allowedFields['TransitionID']);
		foreach ($allowedFields as $field) {
			$fieldName = $field->getName();
			$action->$fieldName = $data[$fieldName];
		}
		$action->write();

		$svc = singleton('WorkflowService');
		if (isset($data['TransitionID']) && $data['TransitionID']) {
			$svc->executeTransition($this->getTarget(), $data['TransitionID']);
		} else {
			// otherwise, just try to execute the current workflow to see if it
			// can now proceed based on user input
			$this->execute();
		}
	}

	/**
	 * Get the object that this workflow is active for.
	 *
	 * Because workflows might not just be on sitetree items, we
	 * need to account for being attached to anything
	 */
	public function getTarget() {
		if ($this->TargetID) {
			return DataObject::get_by_id($this->TargetClass, $this->TargetID);
		}
	}

	/**
	 * @see getTarget
	 */
	public function Target() {
		return $this->getTarget();
	}

	/**
	 * Start a workflow based on a particular definition for a particular object.
	 *
	 * The object is optional; if not specified, it is assumed that this workflow
	 * is simply a task based checklist type of workflow.
	 *
	 * @param WorkflowDefinition $definition
	 * @param DataObject $for
	 */
	public function beginWorkflow(WorkflowDefinition $definition, DataObject $for=null) {
		if(!$this->ID) {
			$this->write();
		}

		if ($for && ($for->hasExtension('WorkflowApplicable') || $for->hasExtension('FileWorkflowApplicable'))) {
			$this->TargetClass = ClassInfo::baseDataClass($for);
			$this->TargetID = $for->ID;
		}

		// lets create the first WorkflowActionInstance.
		$action = $definition->getInitialAction()->getInstanceForWorkflow();
		$action->WorkflowID   = $this->ID;
		$action->write();
		
		$title = $for && $for->hasField('Title') ? 
				sprintf(_t('WorkflowInstance.TITLE_FOR_DO', '%s - %s'), $definition->Title, $for->Title) :
				sprintf(_t('WorkflowInstance.TITLE_STUB', 'Instance #%s of %s'), $this->ID, $definition->Title);

		$this->Title		   = $title;
		$this->DefinitionID    = $definition->ID;
		$this->CurrentActionID = $action->ID;
		$this->InitiatorID     = Member::currentUserID();
		$this->write();

		$this->Users()->addMany($definition->Users());
		$this->Groups()->addMany($definition->Groups());
	}

	/**
	 * Execute this workflow. In rare cases this will actually execute all actions,
	 * but typically, it will stop and wait for the user to input something
	 * 
	 * The basic process is to get the current action, and see whether it has been finished
	 * by some process, if not it attempts to execute it. 
	 * 
	 * If it has been finished, we check to see if there's some transitions to follow. If there's
	 * only one transition, then we execute that immediately. 
	 * 
	 * If there's multiple transitions, we just stop and wait for the user to manually
	 * trigger a transition. 
	 * 
	 * If there's no transitions, we make the assumption that we've finished the workflow and
	 * mark it as such. 
	 * 
	 * 
	 */
	public function execute() {
		if (!$this->CurrentActionID) {
			throw new Exception("Attempted to start an invalid workflow instance #$this->ID!");
		}

		$action     = $this->CurrentAction();
		$transition = false;

		// if the action has already finished, it means it has either multiple (or no
		// transitions at the time), so a subsequent check should be run.
		if($action->Finished) {
			$transition = $this->checkTransitions($action);
		} else {
			$result = $action->BaseAction()->execute($this);

			// if the action was successful, then the action has finished running and
			// next transition should be run (if only one). 
			// input.
			if($result) {
				$action->MemberID = Member::currentUserID();
				$action->Finished = true;
				$action->write();
				$transition = $this->checkTransitions($action);
			}
		}

		// if the action finished, and there's only one available transition then
		// move onto that step - otherwise check if the workflow has finished.
		if($transition) {
			$this->performTransition($transition);
		} else {
			// see if there are any transitions available, even if they are not valid.
			if($action->Finished && !count($action->BaseAction()->Transitions())) {
				$this->WorkflowStatus  = 'Complete';
				$this->CurrentActionID = 0;
			} else {
				$this->WorkflowStatus = 'Paused';
			}

			$this->write();
		}
	}

	/**
	 * Evaluate all the transitions of an action and determine whether we should
	 * follow any of them yet.
	 *
	 * @param  WorkflowActionInstance $action
	 * @return WorkflowTransition
	 */
	protected function checkTransitions(WorkflowActionInstance $action) {
		$transitions = $action->getValidTransitions();
		// if there's JUST ONE transition, then we need should
		// immediately follow it.
		if ($transitions && $transitions->count() == 1) {
			return $transitions->First();
		}
	}

	/**
	 * Transitions a workflow to the next step defined by the given transition.
	 *
	 * After transitioning, the action is 'executed', and next steps
	 * determined. 
	 *
	 * @param WorkflowTransition $transition
	 */
	public function performTransition(WorkflowTransition $transition) {
		// first make sure that the transition is valid to execute!
		$action          = $this->CurrentAction();
		$allTransitions  = $action->BaseAction()->Transitions();

		$valid = $allTransitions->find('ID', $transition->ID);
		if (!$valid) {
			throw new Exception ("Invalid transition state for action #$action->ID");
		}

		$action->actionComplete($transition);

		$definition = DataObject::get_by_id('WorkflowAction', $transition->NextActionID);
		$action = $definition->getInstanceForWorkflow();
		$action->WorkflowID   = $this->ID;
		$action->write();

		$this->CurrentActionID = $action->ID;
		$this->write();
		$this->components = array(); // manually clear the has_one cache

		$action->actionStart($transition);

		$transition->extend('onTransition');
		$this->execute();
	}

	/**
	 * Returns a list of all Members that are assigned to this instance, either directly or via a group.
	 *
	 * @todo   This could be made more efficient.
	 * @return ArrayList
	 */
	public function getAssignedMembers() {
		$list    = new ArrayList();
		$groups  = $this->Groups();

		$list->merge($this->Users());

		foreach($groups as $group) {
			$list->merge($group->Members());
		}

		$list->removeDuplicates();
		return $list;
	}

	public function canView($member=null) {
		return $this->userHasAccess($member);
	}
	public function canEdit($member=null) {
		return $this->userHasAccess($member);
	}
	public function canDelete($member=null) {
		return $this->userHasAccess($member);
	}

	/**
	 * Checks whether the given user is in the list of users assigned to this
	 * workflow
	 *
	 * @param $memberID
	 */
	protected function userHasAccess($member) {
		if (!$member) {
			if (!Member::currentUserID()) {
				return false;
			}
			$member = Member::currentUser();
		}
		
		if(Permission::checkMember($member, "ADMIN")) {
			return true;
		}

		// This method primarily "protects" access to a WorkflowInstance, but assumes access only to be granted to users assigned-to that WorkflowInstance.
		// However; lowly authors (users entering items into a workflow) are not assigned - but we still wish them to see their submitted content.
		$inWorkflowGroupOrUserTables = ($member->inGroups($this->Groups()) 
			|| $this->Users()->find('ID', $member->ID))
			|| ($this->Target()->canView($member) && Permission::check('CMS_ACCESS_CMSMain'));

		// This method is used in more than just the ModelAdmin. Check for the current controller to determine where canView() expectations differ
		if(Controller::curr()->getAction() == 'index' && !$inWorkflowGroupOrUserTables) {
			if($this->getVersionedConnection($this->getTarget()->ID,$member->ID,$this->DefinitionID)) {
				return true;
			}
			return false;
		}
		return $inWorkflowGroupOrUserTables;
	}

	/**
	 * Can documents in the current workflow state be edited?
	 */
	public function canEditTarget() {
		if ($this->CurrentActionID) {
			return $this->CurrentAction()->canEditTarget($this->getTarget());
		}
	}

	/**
	 * Does this action restrict viewing of the document?
	 *
	 * @return boolean
	 */
	public function canViewTarget() {
		$action = $this->CurrentAction();
		if ($action) {
			return $action->canViewTarget($this->getTarget());
		}
		return true;
	}

	/**
	 * Does this action restrict the publishing of a document?
	 *
	 * @return boolean
	 */
	public function canPublishTarget() {
		if ($this->CurrentActionID) {
			return $this->CurrentAction()->canPublishTarget($this->getTarget());
		}
	}
	
	/**
	 * Get the current set of transitions that are valid for the current workflow state
	 * 
	 * @return array 
	 */
	public function validTransitions() {
		$action    = $this->CurrentAction();
		return $action->getValidTransitions();
	}
	
	/* UI RELATED METHODS */

	/**
	 * Gets fields for managing this workflow instance in its current step
	 *
	 * @return FieldList
	 */
	public function getWorkflowFields() {
		$action    = $this->CurrentAction();
		$options   = $this->validTransitions();
		$wfOptions = $options->map('ID', 'Title', ' ');
		$fields    = new FieldList();

		$fields->push(new HeaderField('WorkflowHeader', $action->Title));
		$fields->push(new DropdownField('TransitionID', _t('WorkflowInstance.NEXT_ACTION', 'Next Action'), $wfOptions));

		// Let the Active Action update the fields that the user can interact with so that data can be
		// stored for the workflow.
		$action->updateWorkflowFields($fields);

		return $fields;
	}
	
	/**
	 * Gets Front-End form fields from current Action
	 * 
	 * @return FieldList
	 */
	public function getFrontEndWorkflowFields() {
		$action = $this->CurrentAction();
		
		$fields = new FieldList();
		$action->updateFrontEndWorkflowFields($fields);
		
		return $fields;
	}
	
	/**
	 * Gets Transitions for display as Front-End Form Actions
	 * 
	 * @return FieldList
	 */
	public function getFrontEndWorkflowActions() {
		$action    = $this->CurrentAction();
		$options   = $action->getValidTransitions();
		$actions   = new FieldList();
		
		foreach ($options as $option) {
			$btn = new FormAction("transition_{$option->ID}", $option->Title);
			
			// add cancel class to passive actions, this prevents js validation (using jquery.validate)
			if($option->Type == 'Passive'){
				$btn->addExtraClass('cancel');
			}

			// disable the button if canExecute() returns false
			if(!$option->canExecute($this)){
				$btn = $btn->performReadonlyTransformation();
				$btn->addExtraClass('hide');
			}

			$actions->push($btn);
		}
		
		$action->updateFrontEndWorkflowActions($actions);
		
		return $actions;
	}

	/**
	 * Gets Front-End DataObject
	 * 
	 * @return DataObject
	 */
	public function getFrontEndDataObject() {
		$action = $this->CurrentAction();
		$obj = $action->getFrontEndDataObject();
		
		return $obj;
	}
	
	/**
	 * Gets Front-End DataObject
	 * 
	 * @return DataObject
	 */
	public function getFrontEndRequiredFields() {
		$action = $this->CurrentAction();
		$validator = $action->getRequiredFields();
		
		return $validator;
	}
	
	public function setFrontendFormRequirements() {
		$action = $this->CurrentAction();
		$action->setFrontendFormRequirements();
	}
	
	public function doFrontEndAction(array $data, Form $form, SS_HTTPRequest $request) {
		$action = $this->CurrentAction();
		$action->doFrontEndAction($data, $form, $request);
	}

	/*
	 * We need a way to "associate" an author with this WorkflowInstance and its Target() to see if she is "allowed" to view WorkflowInstances within GridFields
	 * @see {@link $this->userHasAccess()}
	 *
	 * @param number $recordID
	 * @param number $userID
	 * @param number $definitionID
	 * @param number $wasPublished
	 * @return boolean
	 */
	public function getVersionedConnection($recordID,$userID,$definitionID,$wasPublished=0) {
		// Turn this into an array and run through implode()
		$filter = "\"AuthorID\" = '".$userID."' AND \"RecordID\" = '".$recordID."' AND \"WorkflowDefinitionID\" = '".$definitionID."' AND WasPublished = '".$wasPublished."'";
		$query = new SQLQuery();
		$query->setFrom('SiteTree_versions')->setSelect('COUNT(ID)')->setWhere($filter);
		$query->firstRow();
		$hasAuthored = $query->execute();
		if($hasAuthored) {
			return true;
		}
		return false;
	}

	/*
	 * Simple method to retrieve the current action, on the current WorkflowInstance
	 */
	public function getCurrentAction() {
		$join = '"WorkflowAction"."ID" = "WorkflowActionInstance"."BaseActionID"';
		$action = WorkflowAction::get()
					->leftJoin('WorkflowActionInstance',$join)
					->where('"WorkflowActionInstance"."ID" = '.$this->CurrentActionID)
					->first();
		if(!$action) {
			return 'N/A';
		}
		return $action->getField('Title');
	}
}