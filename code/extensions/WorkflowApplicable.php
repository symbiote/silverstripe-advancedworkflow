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

	public static $many_many = array(
		'WorkflowDefinitions' => 'WorkflowDefinition',
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
		$effective = $this->workflowService->getDefinitionsFor($this->owner);

		$tab = $fields->fieldByName('Root') ? $fields->findOrMakeTab('Root.Workflow') : $fields;

		if(Permission::check('APPLY_WORKFLOW')) {
			$config = new GridFieldConfig();
			$config->addComponent(new GridFieldToolbarHeader());
			$config->addComponent(new GridFieldButtonRow('before'));
			$config->addComponent(new GridFieldAddExistingAutocompleter('buttons-before-left'));
			$config->addComponent(new GridFieldDataColumns());
			$config->addComponent(new GridFieldDeleteAction(true));
			$definition = new GridField('WorkflowDefinitions', 'Workflows', $this->owner->WorkflowDefinitions(), $config);
			$tab->push($definition);
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
		if(!(Controller::curr() && Controller::curr()->hasExtension('AdvancedWorkflowExtension'))) {
			return;
		}
		$workflows = $this->workflowService->getWorkflowsFor($this->owner);
		
		$startedWorkflows = array();
		if($workflows->count()) {
			foreach($workflows as $workflow) {
				if(!$this->canEditWorkflow($workflow)) {
					continue;
				}
				$action = FormAction::create('updateworkflow[' . $workflow->ID . ']', $workflow->CurrentAction() ? $workflow->CurrentAction()->Title : _t('WorkflowApplicable.UPDATE_WORKFLOW', 'Update Workflow'))
						->setAttribute('data-icon', 'navigation');
				$actions->fieldByName('MajorActions') ? $actions->fieldByName('MajorActions')->push($action) : $actions->push($action);
				$startedWorkflows[] = $workflow->DefinitionID;
			}
			
		}
		$definitions = $this->workflowService->getDefinitionsFor($this->owner);
		if($definitions->count()) {
			foreach($definitions as $definition) {
				if(in_array($definition->ID, $startedWorkflows)) {
					continue;
				}
				if($definition->getInitialAction()) {
					$action = FormAction::create('startworkflow[' . $definition->ID . ']', $definition->getInitialAction()->Title)
							->setAttribute('data-icon', 'navigation');
					$actions->fieldByName('MajorActions') ? $actions->fieldByName('MajorActions')->push($action) : $actions->push($action);
				}
			}
		}
	}
	
	public function updateFrontendActions($actions){
		$workflows = $this->workflowService->getWorkflowsFor($this->owner);

		$startedWorkflows = array();
		if($workflows->count()) {
			foreach($workflows as $workflow) {
				if(!$this->canEditWorkflow($workflow)) {
					continue;
				}
				$action = FormAction::create('updateworkflow[' . $workflow->ID . ']', $workflow->CurrentAction() ? $workflow->CurrentAction()->Title : _t('WorkflowApplicable.UPDATE_WORKFLOW', 'Update Workflow'))
						->setAttribute('data-icon', 'navigation');
				$actions->fieldByName('MajorActions') ? $actions->fieldByName('MajorActions')->push($action) : $actions->push($action);
				$startedWorkflows[] = $workflow->DefinitionID;
			}
		}
		$definitions = $this->workflowService->getDefinitionsFor($this->owner);
		if($definitions->count()) {
			foreach($definitions as $definition) {
				if(in_array($definition->ID, $startedWorkflows)) {
					continue;
				}
				if($definition->getInitialAction()) {
					$action = FormAction::create('startworkflow[' . $definition->ID . ']', $definition->getInitialAction()->Title)
							->setAttribute('data-icon', 'navigation');
					$actions->fieldByName('MajorActions') ? $actions->fieldByName('MajorActions')->push($action) : $actions->push($action);
				}
			}
		}
	}

	public function AbsoluteEditLink() {
		$CMSEditLink = null;

		if($this->owner instanceof CMSPreviewable) {
			$CMSEditLink = $this->owner->CMSEditLink();
		} else if ($this->owner->hasMethod('WorkflowLink')) {
			$CMSEditLink = $this->owner->WorkflowLink();
		}

		if ($CMSEditLink === null) {
			return null;
		}

		return Controller::join_links(Director::absoluteBaseURL(), $CMSEditLink);
	}
	
	/**
	 * After a workflow item is written, we notify the
	 * workflow so that it can take action if needbe
	 */
	public function onAfterWrite() {
		$instances = $this->getWorkflowInstances();

		if(!$instances->count()) {
			return;
		}
		foreach($instances as $instance) {
			if(!$instances->CurrentActionID) {
				continue;
			}
			$instances->CurrentAction()->BaseAction()->targetUpdated($instance);
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
	 * @param bool $includeComplete 
	 * @return WorkflowInstance
	 */
	public function getWorkflowInstances($includeComplete = false) {
		if (!$this->currentInstance) {
			$this->currentInstance = $this->workflowService->getWorkflowsFor($this->owner, $includeComplete);
		}

		return $this->currentInstance;
	}

	/**
	 * 
	 */
	public function clearWorkflowCache() {
		$this->currentInstance = null;
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
	 * 
	 */
	public function canPublish() {
		foreach($this->getWorkflowInstances() as $instance) {
			if(!$instance->canPublishTarget($this->owner)) {
				return false;
			}
		}

		// otherwise, see if there's any workflows applied. If there are, then we shouldn't be able
		// to directly publish
		if($effective = $this->workflowService->getDefinitionsFor($this->owner)) {
			return false;
		}
		return true;
	}

	/**
	 * Can only edit content that's NOT in another person's content changeset
	 * 
	 */
	public function canEdit($member) {
		$instances = $this->getWorkflowInstances();
		$allowedEdits = 0;
		foreach($instances as $instance) {
			$canEdit = $instance->canEditTarget($this->owner);
			if($canEdit === true) {
				$allowedEdits += 1;
			}
			if($canEdit === false) {
				return false;
			}
		}

		if($allowedEdits == $instances->count()) {
			return true;
		}
		
		return null;
	}

	/**
	 * Can a user edit the current workflow attached to this item?
	 * 
	 * @param $instance
	 */
	public function canEditWorkflow(WorkflowInstance $instance) {
		if(!$instance->canEdit()) {
			return false;
		}
		return true;
	}
}