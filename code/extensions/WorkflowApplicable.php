<?php
/**
 * DataObjects that have the WorkflowApplicable extension can have a
 * workflow definition applied to them. At some point, the workflow definition is then
 * triggered.
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowApplicable extends DataExtension {

	public static $has_one = array(
		'WorkflowDefinition' => 'WorkflowDefinition',
	);
	
	public static $dependencies = array(
		'workflowService'		=> '%$WorkflowService',
	);

	/**
	 * @var WorkflowService
	 */
	public $workflowService;
	
	/**
	 * 
	 * A cache var for the current workflow instance
	 *
	 * @var WorkflowInstance
	 */
	protected $currentInstance;
	
	public function updateSettingsFields(FieldList $fields) {
		$this->updateFields($fields);
	}

	public function updateCMSFields(FieldList $fields) {
		if(!$this->owner->hasMethod('getSettingsFields')) $this->updateFields($fields);
	}

	public function updateFields(FieldList $fields) {
		if (!$this->owner->ID) {
			return $fields;
		}
		$effective = $this->workflowService->getDefinitionFor($this->owner);
		
		$tab       = $fields->fieldByName('Root') ? $fields->findOrMakeTab('Root.Workflow') : $fields;

		if(Permission::check('APPLY_WORKFLOW')) {
			$definition = new DropdownField('WorkflowDefinitionID', _t('WorkflowApplicable.DEFINITION', 'Applied Workflow'));
			$definition->setSource($this->workflowService->getDefinitions()->map());
			$definition->setEmptyString(_t('WorkflowApplicable.INHERIT', 'Inherit from parent'));

			$tab->push($definition);
			
//			$fields->addFieldToTab($tab, $definition);
		}

		$tab->push(new ReadonlyField(
			'EffectiveWorkflow',
			_t('WorkflowApplicable.EFFECTIVE_WORKFLOW', 'Effective Workflow'),
			$effective ? $effective->Title : _t('WorkflowApplicable.NONE', '(none)')
		));

		if($this->owner->ID) {
			$config = new GridFieldConfig_Base();
			$config->addComponent(new GridFieldEditButton());
			$config->addComponent(new GridFieldDetailForm());
			
			$insts = $this->owner->WorkflowInstances();
			$log   = new GridField('WorkflowLog', _t('WorkflowApplicable.WORKFLOWLOG', 'Workflow Log'), $insts, $config);

			$tab->push($log);
		}
	}

	public function updateCMSActions(FieldList $actions) {
		$active = $this->workflowService->getWorkflowFor($this->owner);

		if (Controller::curr() && Controller::curr()->hasExtension('AdvancedWorkflowExtension')){
			if ($active) {
				if ($this->canEditWorkflow()) {
					$action = new FormAction('updateworkflow', $active->CurrentAction() ? $active->CurrentAction()->Title : _t('WorkflowApplicable.UPDATE_WORKFLOW', 'Update Workflow'));
					$action->setAttribute('data-icon', 'navigation');
					$actions->push($action);
				}
			} else {
				$effective = $this->workflowService->getDefinitionFor($this->owner);
				if ($effective && $effective->getInitialAction()) {
					// we can add an action for starting off the workflow at least
					$action = new FormAction('startworkflow', $effective->getInitialAction()->Title);
					$action->setAttribute('data-icon', 'navigation');
					$actions->push($action);
				}
			}
		}
	}
	
	public function updateFrontendActions($actions){
		$active = $this->workflowService->getWorkflowFor($this->owner);

		if ($active) {
			if ($this->canEditWorkflow()) {
				$actions->push(new FormAction('updateworkflow', _t('WorkflowApplicable.UPDATE_WORKFLOW', 'Update Workflow')));
			}
		} else {
			$effective = $this->workflowService->getDefinitionFor($this->owner);
			if ($effective) {
				// we can add an action for starting off the workflow at least
				$initial = $effective->getInitialAction();
				$actions->push(new FormAction('startworkflow', $initial->Title));
			}
		}
	}
	
	/**
	 * After a workflow item is written, we notify the
	 * workflow so that it can take action if needbe
	 */
	public function onAfterWrite() {
		$instance = $this->getWorkflowInstance();
		if ($instance && $instance->CurrentActionID) {
			$action = $instance->CurrentAction()->BaseAction()->targetUpdated($instance);
		}
	}

	public function WorkflowInstances() {
		return WorkflowInstance::get()->filter(array(
			'TargetClass' => $this->ownerBaseClass,
			'TargetID'    => $this->owner->ID
		));
	}

	/**
	 * Gets the current instance of workflow
	 *
	 * @return WorkflowInstance
	 */
	public function getWorkflowInstance() {
		if (!$this->currentInstance) {
			$this->currentInstance = $this->workflowService->getWorkflowFor($this->owner);
		}

		return $this->currentInstance;
	}


	/**
	 * Gets the history of a workflow instance
	 *
	 * @return DataObjectSet
	 */
	public function getWorkflowHistory($limit = null) {
		return $this->workflowService->getWorkflowHistoryFor($this->owner, $limit);
	}

	/**
	 * Check all recent WorkflowActionIntances and return the most recent one with a Comment
	 *
	 * @return WorkflowActionInstance
	 */
	public function RecentWorkflowComment($limit = 10){
		if($actions = $this->getWorkflowHistory($limit)){
			foreach ($actions as $action) {
				if ($action->Comment != '') {
					return $action;
				}
			}
		}
	}

	/**
	 * Content can never be directly publishable if there's a workflow applied.
	 *
	 * If there's an active instance, then it 'might' be publishable
	 */
	public function canPublish() {
		if ($active = $this->getWorkflowInstance()) {
			return $active->canPublishTarget($this->owner);
		}

		// otherwise, see if there's any workflows applied. If there are, then we shouldn't be able
		// to directly publish
		if ($effective = $this->workflowService->getDefinitionFor($this->owner)) {
			return false;
		}

	}

	/**
	 * Can only edit content that's NOT in another person's content changeset
	 */
	public function canEdit($member) {
		if ($active = $this->getWorkflowInstance()) {
			return $active->canEditTarget($this->owner);
		}
	}

	/**
	 * Can a user edit the current workflow attached to this item?
	 */
	public function canEditWorkflow() {
		$active = $this->getWorkflowInstance();
		if ($active) {
			return $active->canEdit();
		}
		return false;
	}
}