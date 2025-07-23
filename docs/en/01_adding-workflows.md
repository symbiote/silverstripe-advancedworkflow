---
title: Adding Workflows to Other Content Objects
summary: Applying workflows to custom models and their corresponding ModelAdmins
icon: project-diagram
---

# Adding workflows to other content objects

In order to apply workflow to other classes (e.g. `MyObject`), you need
to apply it to both the model class and the controller
which is used for editing it. Here's an example for `MyObject`
which is managed through a `MyObjectAdmin` controller,
extending from [`ModelAdmin`](api:SilverStripe\Admin\ModelAdmin).

```yml
App\Models\MyObject:
  extensions:
    - 'Symbiote\AdvancedWorkflow\Extensions\WorkflowApplicable'
App\ModelAdmins\MyObjectAdmin:
  extensions:
    - 'Symbiote\AdvancedWorkflow\Extensions\AdvancedWorkflowExtension'
```

We strongly recommend also setting the [`NotifyUsersWorkflowAction`](api:Symbiote\AdvancedWorkflow\Actions\NotifyUsersWorkflowAction) configuration parameter [`NotifyUsersWorkflowAction.whitelist_template_variables`](api:Symbiote\AdvancedWorkflow\Actions\NotifyUsersWorkflowAction->whitelist_template_variables) to true on new projects. This configuration will achieve this:

```yml
Symbiote\AdvancedWorkflow\Actions\NotifyUsersWorkflowAction:
  whitelist_template_variables: true
```

See the [Security](./04_security.md) section for more details.
