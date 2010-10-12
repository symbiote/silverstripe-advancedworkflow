Advanced Workflow Module
========================

Overview
--------

A module that provides an action / transition approach to workflow, where a
single workflow process is split into multiple configurable states (Actions)
with multiple possible transitions between the actions.

Requirements
------------
*  SilverStripe 2.4+

Installation
------------

Add 

	DataObject::add_extension('SiteTree', 'WorkflowApplicable');

to your site's _config.php file
