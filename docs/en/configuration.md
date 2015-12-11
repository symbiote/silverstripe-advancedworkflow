# Developer documentation

## Configuration

### Adding workflows to other content objects
In order to apply workflow to other classes (e.g. `MyObject`), you need
to apply it to both the model class and the controller
which is used for editing it. Here's an example for `MyObject`
which is managed through a `MyObjectAdmin` controller,
extending from `ModelAdmin`. `mysite/_config/config.yml`:

	:::yml
	MyObject:
	    extensions:
	        - WorkflowApplicable
	MyObjectAdmin:
	    extensions:
	        - AdvancedWorkflowExtension

We strongly recommend also setting the `NotifyUsersWorkflowAction` configuration parameter `whitelist_template_variables` 
to true on new projects. This configuration will achieve this:

	:::yml
	NotifyUsersWorkflowAction:
	    whitelist_template_variables: true

See the Security section below for more details.

### Embargo and Expiry
This add-on functionality allows you to embargo some content changes to only appear as published at some future date. To enable it,
add the `WorkflowEmbargoExpiryExtension`.

	:::yml
	SiteTree:
	    extensions:
	        - WorkflowEmbargoExpiryExtension

Make sure the [QueuedJobs](https://github.com/nyeholt/silverstripe-queuedjobs) 
module is installed and configured correctly.
You should have a cronjob similar to the following in place, running 
as the webserver user.

	*/1 * * * * cd  && sudo -u www php /var/www/framework/cli-script.php dev/tasks/ProcessJobQueueTask

It also allows for an optional subsequent expiry date. Note: Changes to these dates also constitute modifications to the content and as such
are subject to the same workflow approval processes, where a particular workflow instance is in effect. The embargo export functionality can also be used independently of any workflow.

### Sending reminder emails

The workflow engine can send out email reminders if a workflow has been open for longer
than a couple of days (configurable in each "Workflow Definition" through the CMS).

Periodically run the Workflow Reminder Task by adding a task like this one to the crontab:

	# Check every minute if someone needs to be reminded about pending workflows
	*/1 * * * * cd /var/www && sudo -u www ./sapphire/sake dev/tasks/WorkflowReminderTask

This is an example only. The key is to run the task as the same user as the web server.

You can run the task manually for testing by visiting the `/dev/tasks/WorkflowReminderTask` URL of your site.