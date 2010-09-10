<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * Publishes an item
 *
 * @author marcus@silverstripe.com.au
 */
class PublishItemWorkflowAction extends WorkflowAction {

	public static $icon = 'activityworkflow/images/publish.png';

    public function execute() {
		$context = $this->Workflow()->getContext();

		if ($context) {
			// publish it!
			$context->doPublish();
		}

		return true;
	}
}