<?php

namespace Symbiote\AdvancedWorkflow\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowInstance;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowTransition;
use Symbiote\AdvancedWorkflow\Services\WorkflowService;

/**
 * Handles actions triggered from external sources, eg emails or web frontend
 *
 * @author marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class AdvancedWorkflowActionController extends Controller
{
    public function transition($request)
    {
        if (!Security::getCurrentUser()) {
            return Security::permissionFailure(
                $this,
                _t(
                    'AdvancedWorkflowActionController.ACTION_ERROR',
                    "You must be logged in"
                )
            );
        }

        $id = $this->request->requestVar('id');
        $transitionID = (int) $this->request->requestVar('transition');

        $instance = WorkflowInstance::get()->setUseCache(true)->byID((int) $id);
        if ($instance && $instance->canEdit()) {
            $transitionExists = WorkflowTransition::get()->setUseCache(true)->filter('ID', $transitionID)->exists();
            if ($transitionExists) {
                if ($this->request->requestVar('comments')) {
                    $action = $instance->CurrentAction();
                    $action->Comment = $this->request->requestVar('comments');
                    $action->write();
                }

                singleton(WorkflowService::class)->executeTransition($instance->getTarget(), $transitionID);
                $result = array(
                    'success' => true,
                    'link'    => $instance->getTarget()->AbsoluteLink()
                );
                if (Director::is_ajax()) {
                    return json_encode($result);
                }
                return $this->redirect($instance->getTarget()->Link());
            }
        }

        if (Director::is_ajax()) {
            $result = array(
                'success' => false,
            );
            return json_encode($result);
        }

        return $this->redirect($instance->getTarget()->Link());
    }
}
