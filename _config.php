<?php

// Add the following to your config to enable workflow 
// DataObject::add_extension('SiteTree', 'WorkflowApplicable');

Object::add_extension('Member', 'Hierarchy');
Object::add_extension('LeftAndMain', 'ActivityWorkflowExtension');