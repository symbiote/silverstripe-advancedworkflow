<?php
/**
 * A simple approval step that waits for any assigned user to trigger one of the relevant
 * transitions
 *
 * A more complicated workflow might use a majority, quorum or other type of
 * approval functionality
 *
 * @author     marcus@silverstripe.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage actions
 */
class SimpleApprovalWorkflowAction extends WorkflowAction {
	
	public static $icon = 'advancedworkflow/images/approval.png';

    public function execute(WorkflowInstance $workflow) {
		// we don't need to do anything for this execution,
		// as we're relying on the fact that there's at least 2 outbound transitions
		// which will cause the workflow to block and wait. 
		return true;
	}
}
