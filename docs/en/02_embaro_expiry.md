---
title: Embargo and Expiry
summary: Configure embargo and expiry functionality for content changes
icon: calendar-alt
---

# Embargo and expiry

This add-on functionality allows you to embargo some content changes to only appear as published at some future date. To enable it,
add the [`WorkflowEmbargoExpiryExtension`](api:Symbiote\AdvancedWorkflow\Extensions\WorkflowEmbargoExpiryExtension).

```yml
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - 'Symbiote\AdvancedWorkflow\Extensions\WorkflowEmbargoExpiryExtension'
```

Make sure the [Queued Jobs](https://docs.silverstripe.org/en/optional_features/queuedjobs/) module is installed and configured correctly.
You should have a cronjob similar to the following in place, running as the webserver user.

```bash
*/1 * * * * cd  && sudo -u www php /var/www/vendor/bin/sake dev/tasks/ProcessJobQueueTask
```

It also allows for an optional subsequent expiry date. Note: Changes to these dates also constitute modifications to the content and as such
are subject to the same workflow approval processes, where a particular workflow instance is in effect. The embargo export functionality can also be used independently of any workflow.
