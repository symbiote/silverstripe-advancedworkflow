---
title: About workflows
---

# What is a Workflow?
Workflows enforce content changes to go through approval processes before being published.
For example, you might have a team of staff who author content, but they are not allowed to publish that content publicly
until a manager or communications adviser has given approval. This works by limiting access to certain parts of the workflow
process to certain users or groups of users set up in the SilverStripe CMS Security admin.

The CMS can have an unlimited number of workflows created and running within it, but only one workflow can be
attached to a content-object (e.g a "Page") at any one time. Each workflow comprises a number of definable "Actions"
such as "Publish" and "Reject" and each action may have any number of "Transitions" leading out from it
that connect one action to another.

Users and groups can have individual permissions assigned to them on Transitions,
which gives workflow administrators fine-grained control over the content-actions allowed to be performed.
For each transition the current CMS user has permission to enact, a button will appear on the content items assigned to
the current user. The current user can then use this button to initiate the transition.

The same workflow system also allows you to set up embargo and expiry dates on your pages. This enables pages to be assigned a scheduled time
and date when the page will be automatically published to or removed from your website.

## Non-inheritance options

If sitemap inheritance is not what you desire then you can stop inheritance on a specific page type by adding a useInheritedWorkflow method. This method is designed to return a true or false, therefore stopping the inheritance from the parent.

If you need pagetypes to be automatically assigned a specific workflow, we'd recommend adding an extension to the WorkflowDefinition to define the page type(s) it's meant to be used for, and then an onBeforeWrite on the page type, to find and assign that WorkflowDefinition.

## Workflow Terminology
- **Workflow Definition**: Description of all the "actions" and "transitions" that make up a single workflow process to publish a page. Definitions are applied to pages within the CMS and are managed through the "Workflows" section of the CMS.
- **Workflow Instance**: When a user wants to publish a page, instead of selecting the 'publish' button, they instead start a workflow, or more specifically, an instance of the "Workflow Definition" applied to that page. This "Instance" contains all the relevant data (e.g. user choices, comments, etc) for the running workflow on that page's content.
- **Workflow Action**: A workflow can have many actions. Actions describe a single process occurring at each workflow step. Each piece of workflow logic is encapsulated in an action, such as assigning users, publishing a page, or sending notifications.
- **Workflow Transition**: A transition is a 'pathway' between two actions, and defines how a workflow should proceed from one action to another. You can chain transitions, or have the user choose between different ones (e.g. "approve" vs. "reject").
