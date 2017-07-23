<?php
/**
 * A queued job that publishes a target after a delay.
 *
 * @package advancedworkflow
 */
class WorkflowPublishTargetJob extends AbstractQueuedJob {

	public function __construct($obj = null, $type = null, $version = null) {
		if ($obj) {
			$this->setObject($obj);
			$this->publishType = $type ? strtolower($type) : 'publish';
			$this->totalSteps = 1;
			$this->version = $version;
		}
	}

	public function getTitle() {
		return _t(
			'AdvancedWorkflowPublishJob.SCHEDULEJOBTITLE',
			"Scheduled {type} of {object}",
			"",
			array(
				'type' => $this->publishType,
				'object' => $this->getObject()->Title
			)
		);
	}

	public function process() {
        // Ensures we're retrieving the "draft" version of the object to be published 
        \Versioned::reading_stage('Stage');
		if ($target = $this->getObject()) {
			if ($this->publishType == 'publish') {
				$publishMethod = 'doPublish';

				// Check to see if we need to publish a specific version of the DataObject
				if ($target->hasMethod('doVersionPublish') && $this->version !== null) {
					$publishMethod = 'doVersionPublish';
					// Get the specific version that we wish to publish
					$target = Versioned::get_version($target->ClassName, $target->ID, $this->version);
				}

				$target->setIsPublishJobRunning(true);
				$target->PublishOnDate = '';
				$target->writeWithoutVersion();
				$target->$publishMethod();
			} else if ($this->publishType == 'unpublish') {
				$target->setIsPublishJobRunning(true);
				$target->UnPublishOnDate = '';
				$target->writeWithoutVersion();
				$target->doUnpublish();
			}
		}
		$this->currentStep = 1;
		$this->isComplete = true;
	}

}