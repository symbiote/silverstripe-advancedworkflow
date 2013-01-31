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
	
	/**
	 * An array representation of the structure of this workflow template
	 * 
	 * @var array
	 */
	protected $structure;
	
	public function __construct($name, $description = '', $version = '0.0') {
		$this->name = $name;
		$this->description = $description;
		$this->version = $version;
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
	 */
	public function createActions($definition = null) {
		$actions = array();
		$transitions = new ArrayObject();
		$sort = 1;
		foreach ($this->structure as $actionName => $actionTemplate) {
			$action = $this->createAction($actionName, $actionTemplate, $definition);
			// add a sort value in! 
			$action->Sort = $sort++;
			$action->write();
			
			$actions[$actionName] = $action;
			
			$newTransitions = $this->updateActionTransitions($actionTemplate, $action);
			foreach ($newTransitions as $t) {
				$transitions->append($t);
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
			throw new Exception('Invalid action class specified in template');
		}

		$action = $type::create();

		$action->Title = $name;

		if (isset($actionTemplate['properties']) && is_array($actionTemplate['properties'])) {
			foreach ($actionTemplate['properties'] as $prop => $val) {
				$action->$prop = $val;
			}
		}

		if ($definition) {
			$action->WorkflowDefID = $definition->ID;
		}

		$action->write();
		
		return $action;
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
					$target = $transitionTemplate['to'];
				}

				if (isset($transitionMap[$transitionName])) {
					$transition = $transitionMap[$transitionName];
				} else {
					$transition = WorkflowTransition::create();
				}

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
		foreach ($this->structure as $actionName => $actionTemplate) {
			$action = null;
			if (isset($existingActions[$actionName])) {
				$action = $existingActions[$actionName];
			} else {
				$action = $this->createAction($actionName, $actionTemplate, $definition, $transitions);
			}
			
			// add a sort value in! 
			$action->Sort = $sort++;
			$action->write();
			
			$actions[$actionName] = $action;
			
			$newTransitions = $this->updateActionTransitions($actionTemplate, $action);
			foreach ($newTransitions as $t) {
				$transitions->append($t);
			}
		}
		
		foreach ($transitions as $transition) {
			if (isset($actions[$transition->Target])) {
				$transition->NextActionID = $actions[$transition->Target]->ID;
			}
			
			$transition->write();
		}
		
		$definition->TemplateVersion = $this->getVersion();
		$definition->write();
	}
}
