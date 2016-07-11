<?php

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * Handles interactions triggered by users in the backend of the CMS. Replicate this
 * type of functionality wherever you need UI interaction with workflow.
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class AdvancedWorkflowExtension extends LeftAndMainExtension {

	private static $allowed_actions = array(
		'updateworkflow',
		'startworkflow',
        'cancelembargoexpiry',
	);

    /**
     * Handle cancelling the scheduled embargo and expiry dates
     *
     * @param $data
     * @param $form
     * @param $request
     * @return HTMLText|ViewableData_Customised|void
     */
    public function cancelembargoexpiry($data, $form, $request)
    {
        $item = $form->getRecord();

        if (!$item || !Permission::check('CANCEL_EMBARGO_EXPIRY_WORKFLOW')) {
            return;
        }
        $this->saveAsDraftWithAction($form, $item);

        // Shifting scheduled to desired after save draft, since they're not savable fields
        if ($item->hasExtension('WorkflowEmbargoExpiryExtension')) {
            if (!$item->DesiredPublishDate) {
                $item->DesiredPublishDate = $item->PublishOnDate;
            }
            if (!$item->DesiredUnPublishDate) {
                $item->DesiredUnPublishDate = $item->UnPublishOnDate;
            }
            $item->PublishOnDate = '';
            $item->UnPublishOnDate = '';
            $item->clearPublishJob();
            $item->clearUnPublishJob();
            $item->write();
        }

        return $this->returnResponse($form);
    }

	public function startworkflow($data, $form, $request) {
		$item = $form->getRecord();
		$workflowID = isset($data['TriggeredWorkflowID']) ? intval($data['TriggeredWorkflowID']) : 0;

		if (!$item || !$item->canEdit()) {
			return;
		}

		// Save a draft, if the user forgets to do so
		$this->saveAsDraftWithAction($form, $item);

		$svc = singleton('WorkflowService');
		$svc->startWorkflow($item, $workflowID);

		return $this->returnResponse($form);
	}

	/**
	 * Need to update the edit form AFTER it's been transformed to read only so that the workflow stuff is still
	 * allowed to be added with 'write' permissions
	 *
	 * @param Form $form
	 */
	public function updateEditForm(Form $form) {
		Requirements::javascript(ADVANCED_WORKFLOW_DIR . '/javascript/advanced-workflow-cms.js');
		$svc    = singleton('WorkflowService');
		$p      = $form->getRecord();
		$active = $svc->getWorkflowFor($p);

		if ($active) {

			$fields = $form->Fields();
			$current = $active->CurrentAction();
			$wfFields = $active->getWorkflowFields();

			$allowed = array_keys($wfFields->saveableFields());
			$data = array();
			foreach ($allowed as $fieldName) {
				$data[$fieldName] = $current->$fieldName;
			}

			$fields->findOrMakeTab(
				'Root.WorkflowActions',
				_t('Workflow.WorkflowActionsTabTitle', 'Workflow Actions')
			);
			$fields->addFieldsToTab('Root.WorkflowActions', $wfFields);

			$form->loadDataFrom($data);

			if (!$p->canEditWorkflow()) {
				$form->makeReadonly();
			}

			$this->owner->extend('updateWorkflowEditForm', $form);
		}
	}

	public function updateItemEditForm($form) {
		$record = $form->getRecord();
		if ($record && $record->hasExtension('WorkflowApplicable')) {
			$actions = $form->Actions();
			$record->extend('updateCMSActions', $actions);
			$this->updateEditForm($form);
		}
	}

	/**
	 * Update a workflow based on user input.
	 *
	 * @todo refactor with WorkflowInstance::updateWorkflow
	 *
	 * @param array $data
	 * @param Form $form
	 * @param SS_HTTPRequest $request
	 * @return String
	 */
	public function updateworkflow($data, Form $form, $request) {
		$svc = singleton('WorkflowService');
		$p = $form->getRecord();
		$workflow = $svc->getWorkflowFor($p);
		$action = $workflow->CurrentAction();

		if (!$p || !$p->canEditWorkflow()) {
			return;
		}

		$allowedFields = $workflow->getWorkflowFields()->saveableFields();
		unset($allowedFields['TransitionID']);

		$allowed = array_keys($allowedFields);
		if (count($allowed)) {
			$form->saveInto($action, $allowed);
			$action->write();
		}

		if (isset($data['TransitionID']) && $data['TransitionID']) {
			$svc->executeTransition($p, $data['TransitionID']);
		} else {
			// otherwise, just try to execute the current workflow to see if it
			// can now proceed based on user input
			$workflow->execute();
		}

		return $this->returnResponse($form);
	}

	protected function returnResponse($form) {
		if ($this->owner instanceof GridFieldDetailForm_ItemRequest) {
			$record = $form->getRecord();
			if ($record && $record->exists()) {
				return $this->owner->edit($this->owner->getRequest());
			}
		}

		$negotiator = method_exists($this->owner, 'getResponseNegotiator') ? $this->owner->getResponseNegotiator() : Controller::curr()->getResponseNegotiator();
		return $negotiator->respond($this->owner->getRequest());
	}

	/**
	 * Ocassionally users forget to apply their changes via the standard CMS "Save Draft" button,
	 * and select the action button instead - losing their changes.
	 * Calling this from a controller method saves a draft automatically for the user, whenever a workflow action is run.
	 * See: #72 and #77
	 *
	 * @param \Form $form
	 * @param \DataObject $item
	 * @return void
	 */
	protected function saveAsDraftWithAction(Form $form, DataObject $item) {
		$form->saveInto($item);
		$item->write();
	}

}
