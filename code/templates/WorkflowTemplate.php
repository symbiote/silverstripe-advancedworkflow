<?php

/**
 * A class that wraps around an array description of a workflow
 * 
 * array(
 *	'Step Name' = array(
 *		'type'		=> class name
 *		'transitions'	=> array(
 *			'transition name' => 'target step',
 *			'next name' => 'other step'
 *		)
 *	),
 *	'Next Step'	= array(
 *		
 *	),
 * )
 * 
 * This can be defined in config yml as follows
 * 
 * Injector:
 *   SimpleReviewApprove:
 *     class: WorkflowTemplate
 *     constructor:
 *       - Review and Approve
 *       - Description of the workflow template
 *       - 0.1 (version number)
 *     properties:
 *       structure:
 *         Apply for approval:
 *           type: AssignUsersToWorkflowAction
 *           transitions: 
 *             notify: Notify users
 *         Notify users:
 *           type: NotifyUsersWorkflowAction
 *           transitions:
 *             approval: Approval
 *         Approval:
 *           type: SimpleApprovalWorkflowAction
 *           transitions:
 *             Approve: Publish
 *             Reject: Reject
 *         Publish:
 *           type: PublishItemWorkflowAction
 *         Reject:
 *           type: CancelWorkflowAction
 *   WorkflowService:
 *     properties:
 *       templates:
 *         - %$SimpleReviewApprove
 * 
 * When updating a template, there's a few things that can be done to assist
 * the system when changing things around
 * 
 * 1. Update the 'version' number 
 * 
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class WorkflowTemplate {
	protected $name;
	protected $description;
	protected $version;
	protected $remindDays;
	protected $sort;
	
	/**
	 * An array representation of the structure of this workflow template
	 * 
	 * @var array
	 */
	protected $structure;
	
	public function __construct($name, $description = '', $version = '0.0', $remindDays = 0, $sort = 0) {
		$this->name = $name;
		$this->description = $description;
		$this->version = $version;
		$this->remindDays = $remindDays;
		$this->sort = $sort;
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function getVersion() {
		return $this->version;
	}
	
	public function getDescription() {
		return $this->description;
	}
	
	public function getRemindDays() {
		return $this->remindDays;
	}
	
	public function getSort() {
		return $this->sort;
	}		
	
	/**
	 * Set the structure for this template
	 * 
	 * @param array $structure 
	 */
	public function setStructure($structure) {
		$this->structure = $structure;
	}
	
	/**
	 * Creates the relevant data objects for this structure, returning an array
	 * of actions in the order they've been created 
	 * 
	 * @param WorkflowDefinition $definitino
	 *				An optional workflow definition to bind the actions into
	 * @return array
	 */
	public function createRelations($definition = null) {
		$actions = array();
		$transitions = new ArrayObject();
		$sort = 1;
		foreach ($this->structure as $relationName => $relationTemplate) {
			
			$isAction = isset($relationTemplate['type']);
			$isUsers = ($relationName == 'users');
			$isGroups = ($relationName == 'groups');
			
			// Process actions on WorkflowDefinition from the template
			if($isAction) {
				$action = $this->createAction($relationName, $relationTemplate, $definition);
				// add a sort value in! 
				$action->Sort = $sort++;
				$action->write();

				$actions[$relationName] = $action;

				$newTransitions = $this->updateActionTransitions($relationTemplate, $action);
				foreach ($newTransitions as $t) {
					$transitions->append($t);
				}
			}
			// Process users on WorkflowDefinition from the template
			if($isUsers) {
				$this->createUsers($relationTemplate, $definition);
			}
			// Process groups on WorkflowDefinition from the template
			if($isGroups) {
				$this->createGroups($relationTemplate, $definition);
			}			
		}

		foreach ($transitions as $transition) {
			if (isset($actions[$transition->Target])) {
				$transition->NextActionID = $actions[$transition->Target]->ID;
			}
			$transition->write();
		}
		
		return $actions;
	}
	
	/**
	 * Create a workflow action based on a template
	 * 
	 * @param string $name
	 * @param array $template
	 * @param WorkflowDefinition $definition
	 * @return WorkflowAction
	 */
	protected function createAction($name, $actionTemplate, WorkflowDefinition $definition = null) {
		$type = $actionTemplate['type'];
		if (!$type || !class_exists($type)) {
			throw new Exception(_t('WorkflowTemplate.INVALID_TEMPLATE_ACTION', 'Invalid action class specified in template'));
		}

		$action = $type::create();

		$action->Title = $name;

		if (isset($actionTemplate['properties']) && is_array($actionTemplate['properties'])) {
			foreach ($actionTemplate['properties'] as $prop => $val) {
				$action->$prop = $val;
			}
		}
		
		// Deal with User + Group many_many relations on an action
		$this->addManyManyToObject($action, $actionTemplate);		

		if ($definition) {
			$action->WorkflowDefID = $definition->ID;
		}

		$action->write();
		
		return $action;
	}
	
	/**
	 * Create a WorkflowDefinition->Users relation based on template data. But only if the related groups from the
	 * export, can be foud in the target environment's DB.
	 * 
	 * Note: The template gives us a Member Email to work with rather than an ID as it's arguably
	 * more likely that Member Emails will be the same between environments than their IDs.
	 * 
	 * @param array $users
	 * @param WorkflowDefinition $definition
	 * @param boolean $clear
	 * @return void
	 */
	protected function createUsers($users, WorkflowDefinition $definition, $clear = false) {
		// Create the necessary relation in WorkflowDefinition_Users
		$source = array('users' => $users);
		$this->addManyManyToObject($definition, $source, $clear);
	}
	
	/**
	 * Create a WorkflowDefinition->Groups relation based on template data, But only if the related groups from the
	 * export, can be foud in the target environment's DB.
	 * 
	 * Note: The template gives us a Group Title to work with rther than an ID as it's arguably
	 * more likely that Group titles will be the same between environments than their IDs.
	 * 
	 * @param array $groups
	 * @param WorkflowDefinition $definition
	 * @param boolean $clear
	 * @return void
	 */
	protected function createGroups($groups, WorkflowDefinition $definition, $clear = false) {
		// Create the necessary relation in WorkflowDefinition_Groups
		$source = array('groups' => $groups);
		$this->addManyManyToObject($definition, $source, $clear);
	}	
	
	/**
	 * Update the transitions for a given action
	 * 
	 * @param array $actionTemplate
	 * @param WorkflowAction $action
	 * 
	 * @return array
	 */
	protected function updateActionTransitions($actionTemplate, $action) {
		$transitions = array();
		if (isset($actionTemplate['transitions']) && is_array($actionTemplate['transitions'])) {
			
			$existing = $action->Transitions();
			$transitionMap = array();
			foreach ($existing as $transition) {
				$transitionMap[$transition->Title] = $transition;
			}

			foreach ($actionTemplate['transitions'] as $transitionName => $transitionTemplate) {
				$target = $transitionTemplate;
				if (is_array($transitionTemplate)) {
					$to = array_keys($transitionTemplate);
					$transitionName = $to[0];
					$target = $transitionTemplate[$transitionName];
				}

				if (isset($transitionMap[$transitionName])) {
					$transition = $transitionMap[$transitionName];
				} else {
					$transition = WorkflowTransition::create();
				}
				
				// Add Member and Group relations to this Transition
				$this->addManyManyToObject($transition, $transitionTemplate);

				$transition->Title = $transitionName;
				$transition->ActionID = $action->ID;
				// we don't have the NextAction yet other than the target name, so we store that against
				// the transition and do a second pass later on to match things up
				$transition->Target = $target;
				$transitions[] = $transition;
			}
		}

		return $transitions;
	}
	
	/**
	 * Update a workflow definition 
	 * 
	 * @param WorkflowDefinition $definition
	 *				The definition to update
	 */
	public function updateDefinition(WorkflowDefinition $definition) {
		$existingActions = array();
		
		$existing = $definition->Actions()->column('Title');
		$structure = array_keys($this->structure);

		$removeNames = array_diff($existing, $structure);

		foreach ($definition->Actions() as $action) {
			if (in_array($action->Title, $removeNames)) {
				$action->delete();
				continue;
			}
			$existingActions[$action->Title] = $action;
		}
		
		$actions = array();
		$transitions = new ArrayObject;
		$sort = 1;
		// now, go through the structure and create/realign things
		foreach ($this->structure as $relationName => $relationTemplate) {
			
			$isAction = isset($relationTemplate['type']);
			$isUsers = ($relationName == 'users');
			$isGroups = ($relationName == 'groups');
			
			if($isAction) {
				$action = null;
				if (isset($existingActions[$relationName])) {
					$action = $existingActions[$relationName];
				} else {
					$action = $this->createAction($relationName, $relationTemplate, $definition, $transitions);
				}

				// add a sort value in! 
				$action->Sort = $sort++;
				$action->write();

				$actions[$relationName] = $action;

				$newTransitions = $this->updateActionTransitions($relationTemplate, $action);
				foreach ($newTransitions as $t) {
					$transitions->append($t);
				}
			}
			// Process users on WorkflowDefinition from the template
			if($isUsers) {
				$this->createUsers($relationTemplate, $definition, true);
			}
			// Process groups on WorkflowDefinition from the template
			if($isGroups) {
				$this->createGroups($relationTemplate, $definition, true);
			}			
		}
		
		foreach ($transitions as $transition) {
			if (isset($actions[$transition->Target])) {
				$transition->NextActionID = $actions[$transition->Target]->ID;
			}
			$transition->write();
		}
		
		// Set the version and do the write at the end so that we don't trigger an infinite loop!!
		$definition->Description = $this->getDescription();
		$definition->TemplateVersion = $this->getVersion();
		$definition->RemindDays = $this->getRemindDays();
		$definition->Sort = $this->getSort();
		$definition->write();
	}
	
	/**
	 * Given an object, first check it has a ManyMany relation on it and add() Member and Group relations as required.
	 * 
	 * @param Object $object (e.g. WorkflowDefinition, WorkflowAction, WorkflowTransition)
	 * @param array $source Usually data taken from a YAML template
	 * @param boolean $clear Lose/keep Group/Member relations on $object (useful for reloading/refreshing definition)
	 * @return void
	 */
	protected function addManyManyToObject($object, $source, $clear = false) {
		// Check incoming
		if(!is_object($object) || !is_array($source)) {
			return;
		}
		
		// Only some target class variants actually have Group/User relations
		$hasUsers = false;
		$hasGroups = false;
		if($manyMany = $object->stat('many_many')) {
			if(in_array('Member', $manyMany)) {
				$hasUsers = true;
				$userRelationName = array_keys($manyMany);
			}
			if(in_array('Group', $manyMany)) {
				$hasGroups = true;
				$groupRelationName = array_keys($manyMany);
			}			
		}
		
		// Deal with User relations on target object
		if($hasUsers) {
			if($clear) {
				$relName = $userRelationName[0];
				$object->$relName()->removeAll();
			}
			if(isset($source['users']) && is_array($source['users'])) {
				foreach ($source['users'] as $user) {
					$email = Convert::raw2sql($user['email']);
					if($_user = DataObject::get_one('Member', "Email = '".$email."'")) {
						$object->Users()->add($_user);
					}
				}			
			}
		}	
		
		// Deal with Group relations on target object
		if($hasGroups) {
			if($clear) {
				$relName = $groupRelationName[0];
				$object->$relName()->removeAll();
			}			
			if(isset($source['groups']) && is_array($source['groups'])) {
				foreach ($source['groups'] as $group) {
					$title = Convert::raw2sql($group['title']);
					if($_group = DataObject::get_one('Group', "Title = '".$title."'")) {
						$object->Groups()->add($_group);
					}
				}
			}
		}		
	}
}
