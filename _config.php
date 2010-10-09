<?php
/**
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    activityworkflow
 */

// Add the following to your config to enable workflow 
// DataObject::add_extension('SiteTree', 'WorkflowApplicable');

Object::add_extension('Member', 'Hierarchy');
Object::add_extension('LeftAndMain', 'ActivityWorkflowExtension');


if (($ACTIVITY_WORKFLOW_DIR = basename(dirname(__FILE__))) != 'activityworkflow') {
	throw new Exception("The frontend editing module must be in a directory named 'frontend-editing', not $ACTIVITY_WORKFLOW_DIR");
}