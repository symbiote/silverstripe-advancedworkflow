<?php
/**
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    activityworkflow
 */

// Add the following to your config to enable workflow 
// DataObject::add_extension('SiteTree', 'WorkflowApplicable');

Object::add_extension('Member', 'Hierarchy');
Object::add_extension('LeftAndMain', 'ActivityWorkflowExtension');