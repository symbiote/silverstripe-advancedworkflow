<?php

namespace Symbiote\AdvancedWorkflow\Extensions;

use SilverStripe\Control\Controller;
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
    private static $allowed_actions = array(
        'updateworkflow',
        'startworkflow'
    );

    public function startworkflow($data, $form, $request)
    {
        $item = $form->getRecord();
        $workflowID = isset($data['TriggeredWorkflowID']) ? intval($data['TriggeredWorkflowID']) : 0;

        if (!$item || !$item->canEdit()) {
            return;
        }

        // Save a draft, if the user forgets to do so
        $this->saveAsDraftWithAction($form, $item);

        $svc = singleton(WorkflowService::class);
        $svc->startWorkflow($item, $workflowID);

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
        $module = ModuleLoader::getModule('symbiote/silverstripe-advancedworkflow');
        Requirements::javascript($module->getRelativeResourcePath('client/dist/js/advancedworkflow.js'));
        $svc    = singleton(WorkflowService::class);
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

    public function updateItemEditForm($form)
    {
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
     * @return string
     */
    public function updateworkflow($data, Form $form, $request)
    {
        $svc = singleton(WorkflowService::class);
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

    protected function returnResponse($form)
    {
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
