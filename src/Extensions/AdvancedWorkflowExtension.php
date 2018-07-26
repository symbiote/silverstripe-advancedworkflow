<?php

namespace Symbiote\AdvancedWorkflow\Extensions;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\View\Requirements;
use Symbiote\AdvancedWorkflow\Extensions\WorkflowApplicable;
use Symbiote\AdvancedWorkflow\Services\WorkflowService;

/**
 * Handles interactions triggered by users in the backend of the CMS. Replicate this
 * type of functionality wherever you need UI interaction with workflow.
 *
 * @author  marcus@symbiote.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class AdvancedWorkflowExtension extends Extension
{
    private static $allowed_actions = [
        'updateworkflow',
        'startworkflow'
    ];

    /**
     * @param array $data
     * @param Form $form
     * @param HTTPRequest $request
     * @return string|null
     */
    public function startworkflow($data, $form, $request)
    {
        $item = $form->getRecord();
        $workflowID = isset($data['TriggeredWorkflowID']) ? intval($data['TriggeredWorkflowID']) : 0;

        if (!$item || !$item->canEdit()) {
            return null;
        }

        // Save a draft, if the user forgets to do so
        $this->saveAsDraftWithAction($form, $item);

        $service = singleton(WorkflowService::class);
        $service->startWorkflow($item, $workflowID);

        return $this->returnResponse($form);
    }

    /**
     * Need to update the edit form AFTER it's been transformed to read only so that the workflow stuff is still
     * allowed to be added with 'write' permissions
     *
     * @param Form $form
     */
    public function updateEditForm(Form $form)
    {
        Requirements::javascript('symbiote/silverstripe-advancedworkflow:client/dist/js/advancedworkflow.js');
        /** @var WorkflowService $service */
        $service = singleton(WorkflowService::class);
        /** @var DataObject|WorkflowApplicable $record */
        $record = $form->getRecord();
        $active = $service->getWorkflowFor($record);

        if ($active) {
            $fields = $form->Fields();
            $current = $active->CurrentAction();
            $wfFields = $active->getWorkflowFields();

            $allowed = array_keys($wfFields->saveableFields());
            $data = [];
            foreach ($allowed as $fieldName) {
                $data[$fieldName] = $current->$fieldName;
            }

            $fields->findOrMakeTab(
                'Root.WorkflowActions',
                _t('Workflow.WorkflowActionsTabTitle', 'Workflow Actions')
            );
            $fields->addFieldsToTab('Root.WorkflowActions', $wfFields);

            $form->loadDataFrom($data);

            // Set the form to readonly if the current user doesn't have permission to edit the record, and/or it
            // is in a state that requires review
            if (!$record->canEditWorkflow()) {
                $form->makeReadonly();
            }

            $this->owner->extend('updateWorkflowEditForm', $form);
        }
    }

    /**
     * @param Form $form
     */
    public function updateItemEditForm($form)
    {
        /** @var DataObject $record */
        $record = $form->getRecord();
        if ($record && $record->hasExtension(WorkflowApplicable::class)) {
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
     * @param HTTPRequest $request
     * @return string|null
     */
    public function updateworkflow($data, Form $form, $request)
    {
        /** @var WorkflowService $service */
        $service = singleton(WorkflowService::class);
        /** @var DataObject $record */
        $record = $form->getRecord();
        $workflow = $service->getWorkflowFor($record);
        if (!$workflow) {
            return null;
        }

        $action = $workflow->CurrentAction();

        if (!$record || !$record->canEditWorkflow()) {
            return null;
        }

        $allowedFields = $workflow->getWorkflowFields()->saveableFields();
        unset($allowedFields['TransitionID']);

        $allowed = array_keys($allowedFields);
        if (count($allowed)) {
            $form->saveInto($action, $allowed);
            $action->write();
        }

        if (isset($data['TransitionID']) && $data['TransitionID']) {
            $service->executeTransition($record, $data['TransitionID']);
        } else {
            // otherwise, just try to execute the current workflow to see if it
            // can now proceed based on user input
            $workflow->execute();
        }

        return $this->returnResponse($form);
    }

    protected function returnResponse($form)
    {
        if ($this->owner instanceof GridFieldDetailForm_ItemRequest) {
            $record = $form->getRecord();
            if ($record && $record->exists()) {
                return $this->owner->edit($this->owner->getRequest());
            }
        }

        $negotiator = method_exists($this->owner, 'getResponseNegotiator')
            ? $this->owner->getResponseNegotiator()
            : Controller::curr()->getResponseNegotiator();
        return $negotiator->respond($this->owner->getRequest());
    }

    /**
     * Ocassionally users forget to apply their changes via the standard CMS "Save Draft" button,
     * and select the action button instead - losing their changes.
     * Calling this from a controller method saves a draft automatically for the user, whenever a workflow action is run
     * See: #72 and #77
     *
     * @param Form $form
     * @param DataObject $item
     * @return void
     */
    protected function saveAsDraftWithAction(Form $form, DataObject $item)
    {
        $form->saveInto($item);
        $item->write();
    }
}
