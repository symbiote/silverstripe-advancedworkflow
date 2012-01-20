<?php
/**
 * A queued job that publishes a target after a delay.
 *
 * @package advancedworkflow
 */
class WorkflowPublishTargetJob extends AbstractQueuedJob {

	public function __construct($obj = null, $type = null) {
		if ($obj) {
			$this->setObject($obj);
			$this->publishType = $type ? strtolower($type) : 'publish';
			$this->totalSteps = 1;
		}
	}

	public function getTitle() {
		return "Scheduled $this->publishType of " . $this->getObject()->Title;
	}

	public function process() {
		if ($target = $this->getObject()) {
			if ($this->publishType == 'publish') {
				$target->doPublish();
			} else if ($this->publishType == 'unpublish') {
				$target->doUnpublish();
			}
		}
		$this->currentStep = 1;
		$this->isComplete = true;
	}

}