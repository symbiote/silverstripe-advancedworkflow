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
class WorkflowApplicable extends DataObjectDecorator {
	
	/**
	 * 
	 * A cache var for the current workflow instance
	 *
	 * @var WorkflowInstance
	 */
	protected $currentInstance;

	public function extraStatics() {
		return array(
			'has_one' => array(
				'WorkflowDefinition' => 'WorkflowDefinition',
			)
		);
	}

	public function updateCMSFields(FieldSet $fields) {
		$service = singleton('WorkflowService');

		if($effective = $service->getDefinitionFor($this->owner)) {
			$effectiveTitle = $effective->Title;
		} else {
			$effectiveTitle = _t('WorkflowApplicable.NONE', '(none)');
		}

		$allDefinitions = array(_t('WorkflowApplicable.INHERIT', 'Inherit from parent'));

		if($definitions = $service->getDefinitions()) {
			$allDefinitions += $definitions->map();
		}
		
		$tab = $fields->fieldByName('Root') ? 'Root.Workflow' : 'BottomRoot.Workflow';
		
		$fields->addFieldsToTab($tab, array(
			new HeaderField('AppliedWorkflowHeader', _t('WorkflowApplicable.APPLIEDWORKFLOW', 'Applied Workflow')),
			new DropdownField('WorkflowDefinitionID',
				_t('WorkflowApplicable.DEFINITION', 'Applied Workflow'), $allDefinitions),
			new ReadonlyField('EffectiveWorkflow',
				_t('WorkflowApplicable.EFFECTIVE_WORKFLOW', 'Effective Workflow'), $effectiveTitle),
			new HeaderField('WorkflowLogHeader', _t('WorkflowApplicable.WORKFLOWLOG', 'Workflow Log')),
			$logTable = new ComplexTableField(
				$this->owner, 'WorkflowLog', 'WorkflowInstance', null, 'getActionsSummaryFields',
				sprintf('"TargetClass" = \'%s\' AND "TargetID" = %d', $this->owner->class, $this->owner->ID)
			)
		));

		$logTable->setRelationAutoSetting(false);
		$logTable->setPermissions(array('show'));
		$logTable->setPopupSize(760, 420);
	}

	public function updateCMSActions($actions) {
		$svc = singleton('WorkflowService');
		$active = $svc->getWorkflowFor($this->owner);

		if ($active) {
			if ($this->canEditWorkflow()) {
				$actions->push(new FormAction('updateworkflow', _t('WorkflowApplicable.UPDATE_WORKFLOW', 'Update Workflow')));
			}
		} else {
			$effective = $svc->getDefinitionFor($this->owner);
			if ($effective) {
				// we can add an action for starting off the workflow at least
				$initial = $effective->getInitialAction();
				$actions->push(new FormAction('startworkflow', $initial->Title));
			}
		}
	}

	/**
	 * Gets the current instance of workflow
	 *
	 * @return WorkflowInstance
	 */
	public function getWorkflowInstance() {
		if (!$this->currentInstance) {
			$svc = singleton('WorkflowService');
			$this->currentInstance = $svc->getWorkflowFor($this->owner);
		}

		return $this->currentInstance;
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
	}

	/**
	 * Can only edit content that's NOT in another person's content changeset
	 */
	public function canEdit() {
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