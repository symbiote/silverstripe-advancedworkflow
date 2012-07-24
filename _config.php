<?php
/**
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */

// Add the following to your config to enable workflow
// DataObject::add_extension('SiteTree', 'WorkflowApplicable');

Object::add_extension('CMSPageEditController', 'AdvancedWorkflowExtension');

define('ADVANCED_WORKFLOW_DIR', basename(dirname(__FILE__)));

if(ADVANCED_WORKFLOW_DIR != 'advancedworkflow') {
	throw new Exception(
		"The advanced workflow module must be in a directory named 'advancedworkflow', not " . ADVANCED_WORKFLOW_DIR
	);
}

LeftAndMain::require_css(ADVANCED_WORKFLOW_DIR . '/css/AdvancedWorkflowAdmin.css');
