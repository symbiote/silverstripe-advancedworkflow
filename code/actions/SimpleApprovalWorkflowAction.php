<?php

namespace Symbiote\AdvancedWorkflow\Actions;

use Symbiote\AdvancedWorkflow\DataObjects\WorkflowAction;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowInstance;

/**
 * A simple approval step that waits for any assigned user to trigger one of the relevant
 * transitions
 *
 * A more complicated workflow might use a majority, quorum or other type of
 * approval functionality
 *
 * @author     marcus@symbiote.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage actions
 */
class SimpleApprovalWorkflowAction extends WorkflowAction
{
    private static $icon = 'symbiote/silverstripe-advancedworkflow:images/approval.png';

    private static $table_name = 'SimpleApprovalWorkflowAction';

    public function execute(WorkflowInstance $workflow)
    {
        // we don't need to do anything for this execution,
        // as we're relying on the fact that there's at least 2 outbound transitions
        // which will cause the workflow to block and wait.
        return true;
    }
}
