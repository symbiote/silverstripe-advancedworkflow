# Advanced Workflow Module

Note: The SilverStripe 2.4 version of the module is available from the ss24
branch of the repository.

## Overview

A module that provides an action / transition approach to workflow, where a
single workflow process is split into multiple configurable states (Actions)
with multiple possible transitions between the actions.

Please see the wiki at [https://github.com/silverstripe-australia/advancedworkflow/wiki] 
for more info!

## Requirements

* SilverStripe 2.4+
* [Queued Jobs module][1] Required if you use the embargo/expiry functionality

## Installation

Add 

	Object::add_extension('SiteTree', 'WorkflowApplicable');

to your site's _config.php file

To apply workflow to files, add this to _config.php:

	Object::add_extension('File', 'FileWorkflowApplicable');

To enable embargo/expiry (scheduled publish/unpublish), use this:

	Object::add_extension('SiteTree', 'WorkflowEmbargoExpiryExtension');

Make sure the QueuedJobs module is installed and configured correctly - 
you should have a cronjob similar to the following in place

	*/1 * * * * cd  && sudo -u www php /var/www/sapphire/cli-script.php dev/tasks/ProcessJobQueueTask

This is an example only. The key is to run the task as the same user as the web server.

[1]:https://github.com/nyeholt/silverstripe-queuedjobs


