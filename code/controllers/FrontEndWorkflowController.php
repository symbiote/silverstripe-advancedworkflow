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
	
}