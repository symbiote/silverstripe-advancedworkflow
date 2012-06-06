<?php
/**
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */

// Add the following to your config to enable workflow 
// DataObject::add_extension('SiteTree', 'WorkflowApplicable');

Object::add_extension('LeftAndMain', 'AdvancedWorkflowExtension');

if(($MODULE_DIR = basename(dirname(__FILE__))) != 'advancedworkflow') {
	throw new Exception("The advanced workflow module must be in a directory named 'advancedworkflow', not $MODULE_DIR");
}