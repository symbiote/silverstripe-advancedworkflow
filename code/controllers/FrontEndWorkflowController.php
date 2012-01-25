<?php
/**
 * Handles interactions triggered by users in the backend of the CMS. Replicate this
 * type of functionality wherever you need UI interaction with workflow. 
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
abstract class FrontEndWorkflowController extends Controller {

	abstract function start();
		// set workflowdefinitionID
		// save DataObject 
		//$svc = singleton('WorkflowService');
		//$svc->startWorkflow($item);
		//redirect to go/ID
	
	abstract function getContextType();
	
	abstract function getContextObject();
	
	/* Provide method for possible different use cases */
	abstract function getWorkflowDefinition();
	
	public function Form(){
		$svc 			= singleton('WorkflowService');
		$active 		= $svc->getWorkflowFor($this->getContextObject());
		$current 		= $active->CurrentAction();
		$wfFields 		= $active->getFrontEndWorkflowFields();
		$wfActions 		= $active->getFrontEndWorkflowActions();
		$wfValidator 	= $this->getContextObject()->getRequiredFields();
                
		$this->extend('updateFrontendActions', $wfActions);
		$this->extend('updateFrontendFields', $wfFields);
		$this->extend('updateFrontendValidator', $wfValidator);
                
		$form = new Form($this, 'Form', $wfFields, $wfActions, $wfValidator);
		
		if($data = $this->getContextObject()){
			$form->loadDataFrom($data);
		}
        Debug::show('here at form');       
		return $form;
	}
	
}