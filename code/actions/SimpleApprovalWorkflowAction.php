<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * A simple approval step that waits for any assigned user to trigger one of the relevant
 * transitions
 *
 * A more complicated workflow might use a majority, quorum or other type of
 * approval functionality
 *
 * @author marcus@silverstripe.com.au
 */
class SimpleApprovalWorkflowAction extends WorkflowAction {
    public function execute() {
		// we don't need to do anything for this execution,
		// as we're relying on the fact that there's at least 2 outbound transitions
		// which will cause the workflow to block and wait. 
		return true;
	}
}
