<?php
/**
 * Publishes an item
 *
 * @author     marcus@silverstripe.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage actions
 */
class PublishItemWorkflowAction extends WorkflowAction {

	public static $icon = 'advancedworkflow/images/publish.png';

	public function execute(WorkflowInstance $workflow) {
		$target = $workflow->getTarget();

		if ($target) {
			// publish it!
			$target->doPublish();
		}

		return true;
	}
	
	/**
	 * Publish action allows a user who is currently assigned at this point of the workflow to 
	 * 
	 * @param  DataObject $target
	 * @return bool
	 */
	public function canPublishTarget(DataObject $target) {
		return true;
	}
}