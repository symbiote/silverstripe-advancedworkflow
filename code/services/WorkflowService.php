<?php
/**
 * A central point for interacting with workflows
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowService implements PermissionProvider {
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
			if ($dataObject->hasMethod('workflowParent')) {
				$obj = $dataObject->workflowParent();
				if ($obj) {
					return $this->getDefinitionFor($obj);
				}
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
	public function getWorkflowFor($item, $includeComplete = false) {
		$id = $item;

		if ($item instanceof WorkflowAction) {
			$id = $item->WorkflowID;
			return DataObject::get_by_id('WorkflowInstance', $id);
		} else if (is_object($item) && ($item->hasExtension('WorkflowApplicable') || $item->hasExtension('FileWorkflowApplicable'))) {
			$filter = sprintf('"TargetClass" = \'%s\' AND "TargetID" = %d', ClassInfo::baseDataClass($item), $item->ID);
			$complete = $includeComplete ? 'OR "WorkflowStatus" = \'Complete\' ' : '';
			return DataObject::get_one('WorkflowInstance', $filter . ' AND ("WorkflowStatus" = \'Active\' OR "WorkflowStatus"=\'Paused\' ' . $complete . ')');
		}
	}

	/**
	 * Get all the workflow action instances for an item
	 *
	 * @return DataObjectSet
	 */
	public function getWorkflowHistoryFor($item, $limit = null){
		if($active = $this->getWorkflowFor($item, true)){
			$limit = $limit ? "0,$limit" : '';
			return $active->Actions('', 'ID DESC ', null, $limit);	
		}
	}

	/**
	 * Get all the available workflow definitions
	 *
	 * @return DataObjectSet
	 */
	public function getDefinitions() {
		return DataList::create('WorkflowDefinition');
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
	 * Get all the workflows that this user is responsible for
	 * 
	 * @param Member $user 
	 *				The user to get workflows for
	 * 
	 * @return DataObjectSet
	 *				The list of workflow instances this user owns
	 */
	public function usersWorkflows(Member $user) {
		
		$all = new DataObjectSet();
		
		$groupIds = $user->Groups()->column('ID');
		$groupJoin = ' INNER JOIN "WorkflowInstance_Groups" "wig" ON "wig"."WorkflowInstanceID" = "WorkflowInstance"."ID"';
		
		if (is_array($groupIds)) {
			$filter = '("WorkflowStatus" = \'Active\' OR "WorkflowStatus"=\'Paused\') AND "wig"."GroupID" IN (' . implode(',', $groupIds).')';
			$groupAssigned = DataObject::get('WorkflowInstance', $filter, '"Created" DESC', $groupJoin);
			if ($groupAssigned) {
				$all->merge($groupAssigned);
			}
		}

		$userJoin = ' INNER JOIN "WorkflowInstance_Users" "wiu" ON "wiu"."WorkflowInstanceID" = "WorkflowInstance"."ID"';
		$filter = '("WorkflowStatus" = \'Active\' OR "WorkflowStatus"=\'Paused\') AND "wiu"."MemberID" = ' . $user->ID;
		$userAssigned = DataObject::get('WorkflowInstance', $filter, '"Created" DESC', $userJoin);
		if ($userAssigned) {
			$all->merge($userAssigned);
		}
		
		return $all;
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
		$sortVals = array_values($objects->map('ID', 'Sort')->toArray());
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

	public function providePermissions() {
		return array(
			'APPLY_WORKFLOW' => array(
				'name' => _t('AdvancedWorkflow.APPLY_WORKFLOW', 'Apply workflow'),
				'category' => _t('AdvancedWorkflow.ADVANCED_WORKFLOW', 'Advanced Workflow'),
				'help' => _t('AdvancedWorkflow.APPLY_WORKFLOW_HELP', 'Users can apply workflow to items'),
				'sort' => 0
			),
			'VIEW_ACTIVE_WORKFLOWS' => array(
				'name'     => _t('AdvancedWorkflow.VIEWACTIVE', 'View active workflows'),
				'category' => _t('AdvancedWorkflow.ADVANCED_WORKFLOW', 'Advanced Workflow'),
				'help'     => _t('AdvancedWorkflow.VIEWACTIVEHELP', 'Users can view active workflows via the workflows admin panel'),
				'sort'     => 0
			),
			'REASSIGN_ACTIVE_WORKFLOWS' => array(
				'name'     => _t('AdvancedWorkflow.REASSIGNACTIVE', 'Reassign active workflows'),
				'category' => _t('AdvancedWorkflow.ADVANCED_WORKFLOW', 'Advanced Workflow'),
				'help'     => _t('AdvancedWorkflow.REASSIGNACTIVEHELP', 'Reassign active workflows to different users and groups'),
				'sort'     => 0
			)
		);
	}


	
}

class ExistingWorkflowException extends Exception {};
