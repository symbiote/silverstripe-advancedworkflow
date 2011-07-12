<?php
/**
 * A queued job that publishes a target after a delay.
 *
 * @package advancedworkflow
 */
class WorkflowPublishTargetJob extends AbstractQueuedJob {

	public function __construct($class = null, $id = null) {
		$this->class      = $class;
		$this->id         = $id;
		$this->totalSteps = 1;
	}

	public function getTarget() {
		return DataObject::get_by_id($this->class, $this->id);
	}

	public function getTitle() {
		return "Delayed Workflow Publish: {$this->getTarget()->Title}";
	}

	public function process() {
		if ($target = $this->getTarget()) {
			$target->doPublish();
		}

		$this->isComplete = true;
	}

}