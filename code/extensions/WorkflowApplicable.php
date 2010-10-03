<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * DataObjects that have the WorkflowApplicable extension can have a
 * workflow definition applied to them. At some point, the workflow definition is then
 * triggered. 
 *
 * @author marcus@silverstripe.com.au
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
		$svc = singleton('WorkflowService');
		$effective = $svc->getDefinitionFor($this->owner);
		$effectiveTitle = 'None';
		if ($effective) {
			$effectiveTitle = $effective->Title;
		}

		$definitions[] = 'Inherit';
		if($defs = $svc->getDefinitions())foreach ($defs->map() as $id => $title) {
			$definitions[$id] = $title;
		}

		$fields->addFieldsToTab('Root.Workflow', array(
			new HeaderField('AppliedWorkflowHeader', _t('WorkflowApplicable.WORKFLOW', 'Workflow')),
			new DropdownField('WorkflowDefinitionID',
				_t('WorkflowApplicable.DEFINITION', 'Applied Workflow'), $definitions),
			new ReadonlyField('EffectiveWorkflow',
				_t('WorkflowApplicable.EFFECTIVE_WORKFLOW', 'Effective Workflow'), $effectiveTitle),
			new HeaderField('WorkflowLogHeader', _t('WorkflowApplicable.WORKFLOWLOG', 'Workflow Log')),
			$logTable = new ComplexTableField(
				$this->owner, 'WorkflowLog', 'WorkflowInstance', null, 'getActionsSummaryFields'
			)
		));

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
		$active = $this->getWorkflowInstance();
		if ($active) {
			return $active->canPublishTarget();
		}
		return false;
	}

	/**
	 * Cannot directly delete an object from live - it must be deleted from draft, then have the change pushed through
	 * as part of a changeset submission
	 */
//	public function canDeleteFromLive() {
//	}

	/**
	 * Can only edit content that's NOT in another person's content changeset
	 */
	public function canEdit() {
		$active = $this->getWorkflowInstance();
		if ($active) {
			return $active->canEditTarget();
		}
		return true;
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