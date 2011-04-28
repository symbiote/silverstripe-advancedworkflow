<?php
/**
 * A central point for interacting with workflows
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowService {
	public function  __construct() {
	}

	/**
	 * Gets the workflow definition for a given dataobject, if there is one
	 * 
	 * Will recursively query parent elements until it finds one, if available
	 *
	 * @param DataObject $dataObject
	 */
	public function getDefinitionFor(DataObject $dataObject) {
		if ($dataObject->hasExtension('WorkflowApplicable') || $dataObject->hasExtension('FileWorkflowApplicable')) {
			if ($dataObject->WorkflowDefinitionID) {
				return DataObject::get_by_id('WorkflowDefinition', $dataObject->WorkflowDefinitionID);
			}
			if ($dataObject->ParentID) {
				return $this->getDefinitionFor($dataObject->Parent());
			}
		}

		return null;
	}

	/**
	 * Gets the workflow for the given item
	 *
	 * The item can be
	 *
	 * a data object in which case the ActiveWorkflow will be returned,
	 * an action, in which case the Workflow will be returned
	 * an integer, in which case the workflow with that ID will be returned
	 *
	 * @param mixed $item
	 *
	 * @return WorkflowInstance
	 */
	public function getWorkflowFor($item) {
		$id = $item;
		
		if ($item instanceof WorkflowAction) {
			$id = $item->WorkflowID;
			return DataObject::get_by_id('WorkflowInstance', $id);
		} else if (is_object($item) && ($item->hasExtension('WorkflowApplicable') || $item->hasExtension('FileWorkflowApplicable'))) {
			$filter = sprintf('"TargetClass" = \'%s\' AND "TargetID" = %d', $item->ClassName, $item->ID);
			return DataObject::get_one('WorkflowInstance', $filter . ' AND ("WorkflowStatus" = \'Active\' OR "WorkflowStatus"=\'Paused\')');
		}
	}

	/**
	 * Get all the available workflow definitions
	 *
	 * @return DataObjectSet
	 */
	public function getDefinitions() {
		return DataObject::get('WorkflowDefinition');
	}

	/**
	 * Given a transition ID, figure out what should happen to
	 * the given $subject.
	 *
	 * In the normal case, this will load the current workflow instance for the object
	 * and then transition as expected. However, in some cases (eg to start the workflow)
	 * it is necessary to instead create a new instance. 
	 *
	 * @param DataObject $target
	 * @param int $transitionId
	 */
	public function executeTransition(DataObject $target, $transitionId) {
		$workflow   = $this->getWorkflowFor($target);
		$transition = DataObject::get_by_id('WorkflowTransition', $transitionId);

		if(!$transition) {
			throw new Exception("Invalid transition ID $transitionId");
		}

		if(!$workflow) {
			throw new Exception('A transition was executed on a target that does not have a workflow.');
		}

		if($transition->Action()->WorkflowDefID != $workflow->DefinitionID) {
			throw new Exception("Transition #$transition->ID is not attached to workflow #$workflow->ID.");
		}

		$workflow->performTransition($transition);
	}

	/**
	 * Starts the workflow for the given data object, assuming it or a parent has
	 * a definition specified. 
	 * 
	 * @param DataObject $object
	 */
	public function startWorkflow(DataObject $object) {
		$existing = $this->getWorkflowFor($object);
		if ($existing) {
			throw new ExistingWorkflowException("That object already has a workflow running");
		}

		$definition = $this->getDefinitionFor($object);
		if ($definition) {
			$instance = new WorkflowInstance();
			$instance->beginWorkflow($definition, $object);
			$instance->execute();
		}
	}

	/**
	 * Reorders actions within a definition
	 *
	 * @param WorkflowDefinition|WorkflowAction $objects
	 *				The objects to be reordered
	 * @param array $newOrder
	 *				An array of IDs of the actions in the order they should be.
	 */
	public function reorder($objects, $newOrder) {
		$sortVals = array_values($objects->map('ID', 'Sort'));
		sort($sortVals);

		// save the new ID values - but only use existing sort values to prevent
		// conflicts with items not in the table
		foreach($newOrder as $key => $id) {
			if (!$id) {
				continue;
			}
			$object = $objects->find('ID', $id);
			$object->Sort = $sortVals[$key];
			$object->write();
		}
	}
}

class ExistingWorkflowException extends Exception {};
