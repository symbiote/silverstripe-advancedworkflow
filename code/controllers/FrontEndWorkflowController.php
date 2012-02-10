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
		
		$current 		= $active->CurrentAction();
		$wfFields 		= $active->getFrontEndWorkflowFields();
		$wfActions 		= $active->getFrontEndWorkflowActions();
		
		// Let the DataObject control the required fields, rather than each form component/page/action
		$wfValidator 	= $this->getContextObject()->getRequiredFields();
		
		// get any requirements spcific to this contextobject
		if($this->getContextObject()->hasMethod('getFrontendFormRequirements')){
			$this->getContextObject()->getFrontendFormRequirements();
		}
                
		$this->extend('updateFrontendActions', $wfActions);
		$this->extend('updateFrontendFields', $wfFields);
		$this->extend('updateFrontendValidator', $wfValidator);
       
		$form = new FrontendWorkflowForm($this, 'Form', $wfFields, $wfActions, $wfValidator);
		
		$form->addExtraClass("fwf");
		
		if($data = $this->getContextObject()){
			$form->loadDataFrom($data);
		}
    
		return $form;
	}
	
	/**
	 * @return WorkflowTransition
	 */
	protected function getCurrentTransition() {
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
	public function save(array $data, Form $form, SS_HTTPRequest $request) {		
		if (!$obj = $this->getContextObject()) {
			throw new Exception('Context Object Not Found');
		}
		
		//Only Save data when Transition is 'Active'	
		if ($this->getCurrentTransition()->Type == 'Active') {
			if ($obj->canEdit()) {
				$form->saveInto($obj);
				$obj->write();
			}
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