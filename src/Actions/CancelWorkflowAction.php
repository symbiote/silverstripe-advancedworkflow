<?php

namespace Symbiote\AdvancedWorkflow\Actions;

use Symbiote\AdvancedWorkflow\DataObjects\WorkflowAction;

/**
 * Description
 *
 * @author     marcus@symbiote.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage actions
 */
class CancelWorkflowAction extends WorkflowAction
{
    private static $icon = 'symbiote/silverstripe-advancedworkflow:images/cancel.png';

    private static $table_name = 'CancelWorkflowAction';
}
