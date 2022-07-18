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

You can optionally add the [Embargy & Expiry](https://github.com/silverstripe-terraformers/silverstripe-embargo-expiry)
module to your project to allow changes to be published (and/or unpublished) at future dates.

### Sending reminder emails

The workflow engine can send out email reminders if a workflow has been open for longer
than a couple of days (configurable in each "Workflow Definition" through the CMS).

Periodically run the Workflow Reminder Task by adding a task like this one to the crontab:

	# Check every minute if someone needs to be reminded about pending workflows
	*/1 * * * * cd /var/www && sudo -u www ./sapphire/sake dev/tasks/WorkflowReminderTask

This is an example only. The key is to run the task as the same user as the web server.

You can run the task manually for testing by visiting the `/dev/tasks/WorkflowReminderTask` URL of your site.
