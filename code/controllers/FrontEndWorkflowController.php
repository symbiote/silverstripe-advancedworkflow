<?php
/**
 * Provides a front end Form view of the defined Workflow Actions and Transitions 
 *
 * @author  rodney@silverstripe.com.au shea@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
abstract class FrontEndWorkflowController extends Controller {

	protected	$transitionID;
	protected 	$contextObj;
	
	/**
	 * @return string ClassName of object that Workflow is applied to
	 */
	abstract function getContextType();
	
	/**
	 * @return object Context Object
	 */
	protected function getContextObject() {
		if (!$this->contextObj) {
			if ($id = $this->getContextID()) {
				$cType = $this->getContextType();
				$cObj = DataObject::get_by_id($cType, $id);
				$this->contextObj = $cObj->canView() ? $cObj : null;
			}
		}		
		return $this->contextObj;
	}
	
	/**
	 * @return int ID of Context Object
	 */
	protected function getContextID() {
		$id = $this->contextObj ? $this->contextObj->ID : null;
		if (!$id) {
			if ($this->request->param('ID')) {
				$id = (int) $this->request->param('ID');
			} else if ($this->request->requestVar('ID')) {
				$id = (int) $this->request->requestVar('ID');
			}
		}
		return $id;
	}
		
	/**
	 * Specifies the Workflow Definition to be used, 
	 * ie. retrieve from SiteConfig - or wherever it's defined
	 * 
	 * @return WorkflowDefinition
	 */
	 abstract function getWorkflowDefinition();
		
	/**
	 * Handle the Form Action
	 * - FrontEndWorkflowForm contains the logic for this
	 * 
	 * @param SS_HTTPRequest $request
	 * @todo - is this even required???
	 */
	public function handleAction($request){
		return parent::handleAction($request);
	}
	
	/**
	 * Create the Form containing:
	 * - fields from the Context Object
	 * - required fields from the Context Object
	 * - Actions from the connected WorkflowTransitions
	 * 
	 * @return Form
	 */
	public function Form(){
		
		$svc 			= singleton('WorkflowService');
		$active 		= $svc->getWorkflowFor($this->getContextObject());
		
		if (!$active) {
			throw new Exception('Workflow not found, or not specified for Context Object');
		}
		
		$wfFields 		= $active->getFrontEndWorkflowFields();
		$wfActions 		= $active->getFrontEndWorkflowActions();
		$wfValidator 	= $active->getFrontEndRequiredFields();
		
		//Get DataObject for Form (falls back to ContextObject if not defined in WorkflowAction)
		$wfDataObject	= $active->getFrontEndDataObject();
						
		// set any requirements spcific to this contextobject
		$active->setFrontendFormRequirements();
                
		// hooks for decorators
		$this->extend('updateFrontEndWorkflowFields', $wfActions);
		$this->extend('updateFrontEndWorkflowActions', $wfFields);
		$this->extend('updateFrontEndRequiredFields', $wfValidator);
		$this->extend('updateFrontendFormRequirements');
       
		$form = new FrontendWorkflowForm($this, 'Form/' . $this->getContextID(), $wfFields, $wfActions, $wfValidator);
		
		$form->addExtraClass("fwf");
		
		if($wfDataObject) {
			$form->loadDataFrom($wfDataObject);
		}
    
		return $form;
	}
	
	/**
	 * @return WorkflowTransition
	 */
	public function getCurrentTransition() {
		$trans = null;
		if ($this->transitionID) {
			$trans = DataObject::get_by_id('WorkflowTransition',$this->transitionID);
		}
		return $trans;
	}
	
	/**
	 * Save the Form Data to the defined Context Object
	 * 
	 * @param array $data
	 * @param Form $form
	 * @param SS_HTTPRequest $request
	 * @throws Exception
	 */
	public function doFrontEndAction(array $data, Form $form, SS_HTTPRequest $request) {
		if (!$obj = $this->getContextObject()) {
			throw new Exception('Context Object Not Found');
		}
		
		//Only Save data when Transition is 'Active'
		if ($this->getCurrentTransition()->Type == 'Active') {
			//Hand off to WorkflowAction to perform Save
			$svc 			= singleton('WorkflowService');
			$active 		= $svc->getWorkflowFor($obj);
			
			$active->doFrontEndAction($data, $form, $request);
		}
		
		//run execute on WorkflowInstance instance		
		$action = $this->contextObj->getWorkflowInstance()->currentAction();
		$action->BaseAction()->execute($this->contextObj->getWorkflowInstance());
		
		//get valid transitions
		$transitions = $action->getValidTransitions();
		
		//tell instance to execute transition if it's in the permitted list
		if ($transitions->find('ID',$this->transitionID)) {
			$this->contextObj->getWorkflowInstance()->performTransition($this->getCurrentTransition());
		}
	}
	
}