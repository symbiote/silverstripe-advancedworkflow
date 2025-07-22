---
title: Sending Reminder Emails
summary: Configuring automated email reminders for pending workflows
icon: envelope
---

# Sending reminder emails

The workflow engine can send out email reminders if a workflow has been open for longer
than a couple of days (configurable in each "Workflow Definition" through the CMS).

Periodically run the Workflow Reminder Task by adding a task like this one to the crontab:

```bash
# Check every minute if someone needs to be reminded about pending workflows
*/1 * * * * cd /var/www && vendor/bin/sake dev/tasks/WorkflowReminderTask
```

This is an example only. The key is to run the task as the same user as the web server.

You can run the task manually for testing by visiting the `/dev/tasks/WorkflowReminderTask` URL of your site.
