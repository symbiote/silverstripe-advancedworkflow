<?php

/**
 * Handles actions triggered from external sources, eg emails or web frontend
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class AdvancedWorkflowActionController extends Controller {
	
	public function transition($request) {
		if (!Member::currentUserID()) {
			return Security::permissionFailure($this, "You must be logged in");
		}

		$id = $this->request->requestVar('id');
		$transition = $this->request->requestVar('transition');

		$instance = DataObject::get_by_id('WorkflowInstance', (int) $id);
		if ($instance && $instance->canEdit()) {
			$transition = DataObject::get_by_id('WorkflowTransition', (int) $transition);
			if ($transition) {
				if ($this->request->requestVar('comments')) {
					$action = $instance->CurrentAction();
					$action->Comment = $this->request->requestVar('comments');
					$action->write();
				}

				singleton('WorkflowService')->executeTransition($instance->getTarget(), $transition->ID);
				$result = array(
					'success'	=> true,
					'link'		=> $instance->getTarget()->AbsoluteLink()
				);
				if (Director::is_ajax()) {
					return Convert::raw2json($result);
				} else {
					return $this->redirect($instance->getTarget()->Link());
				}
			}
		}

		if (Director::is_ajax()) {
			$result = array(
				'success'		=> false,
			);
			return Convert::raw2json($result);
		} else {
			$this->redirect($instance->getTarget()->Link());
		}
	}
}
