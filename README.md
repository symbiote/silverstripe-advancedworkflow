Advanced Workflow Module
========================

Overview
--------

A module that provides an action / transition approach to workflow, where a
single workflow process is split into multiple configurable states (Actions)
with multiple possible transitions between the actions.

Requirements
------------
* SilverStripe 2.4+
* [Queued Jobs module][1]

Installation
------------

Add 

	Object::add_extension('SiteTree', 'WorkflowApplicable');

to your site's _config.php file

To apply workflow to files, add this to _config.php:

	Object::add_extension('File', 'FileWorkflowApplicable');

To enable embargo/expiry (scheduled publish/unpublish), use this:

	Object::add_extension('SiteTree', 'WorkflowEmbargoExpiryExtension');

Periodically run the Process Job Queue Task by adding a task like this one to the crontab:

	*/1 * * * * cd /var/www && sudo -u www ./sapphire/sake dev/tasks/ProcessJobQueueTask

This is an example only. The key is to run the task as the same user as the web server.

You can run the task manually for testing by visiting the `/dev/tasks/ProcessJobQueueTask` URL of your site.

[1]:https://github.com/nyeholt/silverstripe-queuedjobs


