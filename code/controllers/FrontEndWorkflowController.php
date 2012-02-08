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

	public $transitionID;
	
	protected $contextObj;

	abstract function start();
	
	abstract function getContextType();
	
	abstract function getContextObject();
	
	/* Provide method for possible different use cases */
	abstract function getWorkflowDefinition();
		
	public function Form(){
		$svc 			= singleton('WorkflowService');
		if (!($active = $svc->getWorkflowFor($this->getContextObject()))) {
			throw new Exception('Workflow not found, or not specified for Context Object');
		}
		$current 		= $active->CurrentAction();
		$wfFields 		= $active->getFrontEndWorkflowFields();
		$wfActions 		= $active->getFrontEndWorkflowActions();
		
		//@todo - evaluate whether or not this should be done via the ActionInstance->BaseAction in the same manner as the Fields & Actions 
		// does SS require validation for a field if it's not actually rendered? (ie. multi-page form)
		$wfValidator 	= $this->getContextObject()->getRequiredFields();
                
		$this->extend('updateFrontendActions', $wfActions);
		$this->extend('updateFrontendFields', $wfFields);
		$this->extend('updateFrontendValidator', $wfValidator);
                
		$form = new FrontendWorkflowForm($this, 'Form', $wfFields, $wfActions, $wfValidator);
		
		if($data = $this->getContextObject()){
			$form->loadDataFrom($data);
		}
    
		return $form;
	}
	
	public function getCurrentTransition() {
		$trans = null;
		
		if ($this->transitionID) {
			$trans = DataObject::get_by_id('WorkflowTransition',$this->transitionID);
		}
		
		return $trans;
	}
	
	/* Save the form Data */
	abstract function save(array $data, Form $form, SS_HTTPRequest $request);
	
}