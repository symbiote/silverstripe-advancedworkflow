<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * A WorkflowInstance is created whenever a user wants to 'publish' or
 *
 * @author marcus@silverstripe.com.au
 */
class WorkflowInstance extends DataObject {
    public static $db = array(
		'Title' => 'Varchar(128)',
		'WorkflowStatus' => "Enum('Active,Paused,Complete,Cancelled','Active')",
		'TargetClass' => 'Varchar(64)',
		'TargetID' => 'Int',
	);

	public static $has_one = array(
		'Definition' => 'WorkflowDefinition',
		'CurrentAction' => 'WorkflowAction',
	);

	public static $has_many = array(
		'Actions' => 'WorkflowAction',
	);

	/**
	 * The list of users who are responsible for performing the current WorkflowAction
	 *
	 * @var array
	 */
	public static $many_many = array(
		'Users' => 'Member',
		'Groups' => 'Group'
	);

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
	 * Gets the item that this workflow is being executed against.
	 *
	 * @return DataObject
	 */
	public function getContext() {
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
		// make sure to have an ID first!!!
		if (!$this->ID) {
			$this->write();
		}
		
		if ($for && Object::has_extension($for->ClassName, 'WorkflowApplicable')) {
			$this->TargetClass = $for->ClassName;
			$this->TargetID = $for->ID;
			$for->write();
		}

		$this->Title = sprintf(_t('WorkflowInstance.TITLE_STUB', 'Instance #%s of %s'), $this->ID, $definition->Title);

		$users = $definition->Users();
		if ($users->Count()) {
			foreach ($users as $user) {
				$this->Users()->add($user);
			}
		}

		$groups = $definition->Groups();
		if ($groups->Count()) {
			foreach ($groups as $group) {
				$this->Groups()->add($group);
			}
		}

		$actionMapping = array();
		$actions = $definition->getSortedActions();
				
		if ($actions) {
			foreach ($actions as $action) {
				$newAction = $action->duplicate(false);
				$newAction->WorkflowDefID = 0;
				$newAction->WorkflowID = $this->ID;
				$newAction->Sort = 0;
				$newAction->write();
				$newAction->cloneFromDefinition($action);

				$actionMapping[$action->ID] = $newAction->ID;

				if (!$this->CurrentActionID) {
					$this->CurrentActionID = $newAction->ID;
				}
			}

			// iterate again, so we can clone the action transitions with appropriate
			// mappings
			foreach ($actions as $action) {
				$transitions = $action->getAllTransitions();
				if ($transitions) {
					foreach ($transitions as $transition) {
						$newTransition = $transition->duplicate(false);
						$newTransition->ActionID = $actionMapping[$transition->ActionID];
						$newTransition->NextActionID = $actionMapping[$transition->NextActionID];
						$newTransition->write();
						
						$newTransition->cloneFromDefinition($transition);
					}
				}
			}
		}

		$this->write();
	}

	/**
	 * Execute this workflow. In rare cases this will actually execute all actions,
	 * but typically, it will stop and wait for
	 */
	public function execute() {
		if (!$this->CurrentActionID) {
			singleton('WfUtils')->log("Workflow #$this->ID appears to be invalid: attempted to execute() without current action", SS_Log::ERR);
			throw new Exception("Attempted to start an invalid workflow instance #$this->ID!");
		}

		$currentAction = $this->CurrentAction();
		// see if it's been executed. If it has, it means that the action has multiple
		// transitions (or no transitions at the time) so we should do a
		// subsequent check now.
		$availableTransition = null;
		if ($currentAction->Executed) {
			// see if there's any transitions we can make use of.
			$availableTransition = $this->checkTransitions($currentAction);
		} else {
			// otherwise, lets execute the current action
			$result = $currentAction->execute();

			// if the execution was successful (ie everything finished as expected)
			// the we can go ahead and check for the next transition
			// if not, it means the action is still waiting on either time or user input
			if ($result) {
				$currentAction->Executed = true;
				$currentAction->write();
				$availableTransition = $this->checkTransitions($currentAction);
			}
		}

		// okay, if there's an available transition straight from the execute, then lets
		// do that. Otherwise, check to see whether this is actually the last step
		// entirely
		if ($availableTransition) {
			$this->performTransition($availableTransition);
		} else {
			// see if there are ANY transitions for the action, not just if there's a valid one
			$all = $currentAction->getAllTransitions();
			if ($currentAction->Executed && !$all || $all->Count() == 0) {
				$this->completeWorkflow();
			} else {
				$this->WorkflowStatus = 'Paused';
				$this->write();
			}
		}
	}

	/**
	 * Mark the workflow as complete
	 *
	 * @param String $status
	 *				The status of the completed workflow. Either Complete or Cancelled.
	 */
	public function completeWorkflow($status = 'Complete') {
		// we're finished... !
		$this->CurrentActionID = 0;
		$this->WorkflowStatus = $status;

		$this->write();
	}

	/**
	 * Evaluate all the transitions of an action and determine whether we should
	 * follow any of them yet. 
	 *
	 * @param WorkflowAction $action
	 */
	protected function checkTransitions(WorkflowAction $action) {
		$transitions = $action->getNextTransitions();
		// if there's JUST ONE transition, then we need should
		// immediately follow it.
		if ($transitions && $transitions->Count() == 1) {
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
		// we'll update our CurrentAction to the new value and execute again
		$this->CurrentActionID = $transition->NextActionID;
		$this->write();
		$this->flushCache();

		$transition->extend('onTransition');

		$this->execute();
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

		$memberGroups = $member->Groups();
		/* @var $memberGroups DataObjectSet */
		if ($memberGroups) {
			$groups = $this->Groups();
			if ($groups) {
				// see if they're in it
				foreach ($groups as $group) {
					if ($match = $memberGroups->find('ID', $group->ID)) {
						return true;
					}
				}
			}
		}

		$users = $this->Users();
		
		if ($users && $user = $users->find('ID', $member->ID)) {
			return true;
		}
		return false;
	}

	/**
	 * Can documents in the current workflow state be edited?
	 */
	public function canEditTarget() {
		$action = $this->CurrentAction();
		if ($action) {
			return $action->canEdit();
		}
		return true;
	}

	/**
	 * Does this action restrict viewing of the document?
	 *
	 * @return boolean
	 */
	public function canViewTarget() {
		$action = $this->CurrentAction();
		if ($action) {
			return $action->canView();
		}
		return true;
	}

	/**
	 * Does this action restrict the publishing of a document?
	 *
	 * @return boolean
	 */
	public function canPublishTarget() {
		$action = $this->CurrentAction();
		if ($action) {
			return $action->canPublish();
		}
		return true;
	}

	/**
	 * Overridden because dataobject doesn't clear out components (just componentCache??)
	 *
	 * @param boolean $persistant
	 */
	public function flushCache($persistent=true) {
		parent::flushCache($persistent);
		$this->components = array();
	}
}
