* Understand workflow
* Learn how to request publication (as a content author)
* Learn how to publish content (as an "editor" and "manager")
* Learn how to create a simple 2-step workflow

# Understanding workflow:

In its most basic sense, workflow means that new content or changes to existing content, need to go through an approval process before they're able to be
published to the live site. This works by limiting access to certain parts of the workflow to certain users, using the standard SilverStripe CMS Security admin.

The CMS can have an unlimited number of workflows created and running within it, but only one workflow can be attached to a content-object (.e.g a "Page")
at any one time.

Each workflow comprises a number of definable "Actions" such as "Publish" and "Reject" and each action may have any number of "Transitions" leading out from it
that connect one action to another.

Users and groups can have individual permissions assigned to them on Transitions, which gives workflow administrators fine-grained control over the content-actions
allowed to be performed.

As of SilverStripe 3.1, the button management within the CMS differs to previous versions in the 3.x series.
Generally, each transition the current CMS user has permission to enact, will be available to them as a button on each item of content assigned to them.
The labels of these are taken from transition names.

# Requesting publication (authors)

Depending on the specific permissions content authors have, authors may only have the ability to create or edit content and to request publication,
but not to actually publish content - making it publicly viewable - themselves.

## Content Author - Non-admin

To request publication, save your page using the "Save Draft" button on the bottom menu of the Editing Pane.

Once your content has been saved within the CMS, you can then request publication by pressing the "Apply for Approval" button (or equivalent, depending on
how your workflow has been setup) in the same bottom menu of the Editing Pane.

## Content Publisher - Admin

You will be asked to add a comment about your edits. This comment will become a part of the audit trail for your content, and we recommend adding this information.
However, it is not enforced, and can you can safely proceed while leaving this field blank.

Depending on how your workflow is setup, users and/or groups configured with "approval" permissions will be alerted via email and be able to login to the CMS and
see your page as a pending approval request when they view the workflow admin. The publisher will then need to review your request and if happy, will likely
publish it.   

# Approving and publishing content (editors / managers)

Depending on the setup of workflow, publishers may receive an e-mail when authors have requested publication. Publishers then select the 'Workflow' navigation
item to view a report of pending items and by selecting one form the list, be able to access the approval step.

In the following screen, the drop-down menu will display the next approval step based on the workflow for this page.

Publishers are also able to enter in comments here, detailing their approval or cancel/deny the approval if necessary.

# Creating a simple workflow

Before creating your new workflow, it's worth reviewing some of the terminology being used:

## Workflow Definition

This is the description of all the "actions" and "transitions" comprising a single workflow.

It is these that are applied to pages within the CMS and are managed through the "Workflows" section of the CMS.

## Workflow Instance

When a user wants to publish a page, instead of selecting the 'publish' button, they instead start a workflow, or more specifically, an instance of the
Workflow Definition applied to that page. This "Instance" contains all the relevant data (e.g. user choices, comments, etc) for the running workflow
on that content.

## Workflow Action

A workflow can have many actions. Actions describe a single process occurring at each workflow step. Each piece of workflow logic is encapsulated in an action,
such as;

* Assigning users
* Publishing a page
* Sending notifications

## Workflow Transition

A transition is a 'pathway' between two actions, and defines how a workflow should proceed from one action to another.

Sometimes an action will have a single transition from itself to the next action; when the workflow begins execution,
these actions are executed immediately one after another. An example of this might be when you want to assign a user to the workflow,
then notify them immediately; the Assign action will have a single transition to the Notify action.

If you want the user to make an explicit choice about which path of the workflow to move to after a certain action,
there should be multiple transitions created going out from that action. Continuing the above flow; after Notifying users,
you might want them to make a decision as to whether to Approve or Reject the item; therefore, from the Notify action, there may be 2 transitions; 

1). The Approve transition that leads to the approval and publication actions.
2). The Reject transition that leads to the cancel action.

The name given to a transition appears on the "Workflow Actions" tab of a content item when a content author needs to make a decision.

Each action may have an arbitrary number of outbound transitions, and transitions can loop around back to earlier parts of the workflow too!

Here, we'll create a simple two step workflow that;

1. Assigns a content-change to the "Editors" group for initial approval
2. Notifies the editors of their pending content change
3. Upon Editor approval, assigns the change to the "Manager" group for final approval
4. Notifies the Managers of their pending content change
5. Upon Manager approval, the content-item is Published
6. Upon Manager rejection, the content item is not published and the workflow is cancelled

# Notes:

* Before starting, ensure you have "Authors", "Editors" and "Managers" groups created with a single user linked to each group, using the standard CMS Security Admin.
* While still in the Security Admin, ensure all 3 workflow-specific permissions are checked for each of these groups, so that each group's users, inherit the same permissions should you wish to add further authors, editors or managers with minimum additional effort later. Note: If you omit this step, users will be unable to perform approval/rejection steps. 
* It is possible to assign entire workflows and individual workflow-actions to Groups of users and individual users.

# Procedure:

Assuming you're logged-in as a CMS administrator with full admin rights - select the "Workflows" link on the left-hand CMS navigation.
We'll now create our workflow definition; defining all the required actions and joining them all up with transitions:

* Select the "Add Workflow Definition" button
* Enter a title e.g "My New Workflow"
* Enter a workflow description to describe your workflow and its intended steps e.g "A simple 2-step workflow for publishing content."
* Select the "Create" button.

## Creating workflow actions

1. You should have noticed a new item appear in the main CMS content-area entitled; "Workflow". 
2. From its drop-down menu, select the "Assign Users To Workflow Action" option and then select the "Create" button.
3. In the popup dialogue that appears, type the title: "Apply for approval" (Note: the first workflow action's title is used for the apply button's label in the CMS UI)
4. Leave everything else as-is, but in the "Groups" drop-down menu - locate and select the "Editors" group, then select "Save"
5. Create another action, this time selecting the "Notify Users Workflow Action" option. Call it "Notify Editors". Leave everything-else as-is, but because this action sends emails, enter an email subject, a from-address and email-body template (see the "Formatting Help" menu at the bottom of the popup dialogue), then click Save.
6. Create another action and select the "Simple Approval Workflow Action" option, select the "Create" button and in the popup, give it the title "Editor Approval", then select "Save". Note: in a simple workflow such as this, the approval action doesn't actually do anything. However, it's good practice to have it as it provides a clear reference point of where approval occurs. In a more complex workflow, you might use a "Counting Approval" action to do things like count the number of people who have approved.
7. Create another action "Assign Users To Workflow Action", and entitle this one "Assign Managers". Leave everything else as-is and locate and select the "Managers" group from the "Groups" drop-down, then select "Save".
8. Create another action "Notify Users Workflow Action", and name it "Notify Managers". Leave everything-else as-is, but like the previous "Notify Users Workflow Action", enter the email-specific details, then select "Save".
9. Create another action "Simple Approval Workflow Action" and name it "Manager Approval", selecting "Save" when you’re done.
10. Create another action "Publish Item Workflow Action" and call it "Publish item", leave everything else as-is and select "Save" when you’re done.
11. Create another action "Cancel Workflow Action", call it "Cancel", leave everything else as-is, and select "Save" when you’re done.

Okay, now we need to join up these actions using transitions, so that users can make the appropriate choices.

## Creating workflow transitions

* On your "Apply for approval" action in the list of actions you just created in the above step, select the "Add Transition" button and in the popup dialogue that appears, entitle the transition "Send Notification", leave everything else as-is and select "Notify Editors" as the "Next Action", then select the "Save" button.
* On your "Notify Editors" action, select the "Add Transition button and call this one "Wait for approval" and select "Editor Approval" as the "Next Action", then select the "Save" button.
* On your "Editor Approval" action, select the "Add Transition" button and call this one "Approve", select "Assign Managers" as the "Next Action".
* Create another transition on the "Editor Approval" action, call this one "Reject" and select "Cancel" as the "Next Action", then select the "Save" button - you've just created your first decision point.
* On the "Assign Managers" action, add a transition and call it "Notify Managers", select "Notify Managers" as the next action, then select "Save".
* On the "Notify Managers" action, add a transition and call it "Wait for approval" and select "Manager Approval" as the next action, then select "Save".
* On the "Manager Approval" action, add a transition and call it "Accept and Publish" and select "Publish item" as the next action.
* Create another transition on the "Manager Approval" action and call it "Reject and Cancel", then select "Cancel" as the next action, then select "Save".
* Select the "Save" button at the bottom of the screen to finalize your workflow, and you're done.

## Assigning workflow to content

At this point your new workflow isn't much use if your existing CMS content isn't aware of it. Select the "Pages" left-hand menu item,
create new page called "Workflow Test", then select the "Settings" tab and then the "Workflow" tab. Note: the default workflow selection is "Inherit from parent"
so you can assign a workflow to a parent page and each child page will use that workflow without any further configuration.

However, you might want one specific page to use a different workflow than its parent, so in this case select the "My New Workflow" option from the drop-down menu
and select "Save draft" or "Save and Publish" as usual.

Your new workflow has now been associated with your new page. If you select the "Content" tab, you should notice a new button available labeled as per your
first workflow action, which should be "Apply for approval" - if you've followed the instructions above.

## Testing and using your new workflow

Logout of the CMS and log back in again as your "Author" user (see the "Notes" section above)

Navigate to the page you created in the previous step, enter some text into the "Content" area and then select the "Save Draft" button.
Once the page had reloaded, ''then'' select the "Apply for approval" button, now depending on users and groups assigned to your transitions and
content-permissions, the content may now be locked from further editing until it has progressed through the workflow instance to the final Manager's
"Publish item" action.

Notice that if you select the "Workflows" left-hand navigation menu item once again, you should see a list of your "Submitted items".
At this point this should be showing a single entry for the "Workflow Test" page. You can refer back to this list and observe the "Current action" column
update as your changes progress though the workflow.

Depending on how you configured the email address for the Editor user you created in the Security admin before starting, an email should have been received
in the Editor's email inbox alerting them that some new content is available for review/approval by them.

Now logout of the CMS and log back in again as a user from the "Editors" group. Select the "Workflows" left-hand navigation menu item, and notice that you have
a similar list to before, but this one is entitled "Your pending items". These are the workflow changes automatically assigned to you that are now awaiting your 
attention.

Click anywhere on the table row and in the "Next Action" drop-down menu, make your choice as Editor, then select "Save". You can also see a log of the actions.
Note: You are also able to select the blue content-title text to view the page itself within the CMS and manually review the changes before committing to
accepting or rejecting them. If you now go back to the "Workflow" admin, you'll notice that the items that was in the "Your Pending Items" list, is no longer
there, as the action is now with the users of the "Managers" group.

Logout of the CMS, and login as your "Manager" user. Select the "Workflows" left-hand navigation menu item, and notice that there is a similar list to before
entitled "Your pending items", again with the "Workflow Test" page being the only item. Click on this item and make your selection as manager, and then select
the "Save" button.

If you were to go and check the edit screen for this page, you'll notice now that having gone through the full workflow, that the action button at the bottom,
now shows "Apply for approval" once again, and logging back-in as an Author, this user is now able to make further changes.

# Embargo and Expiry

Once enabled, this add-on functionality allows you to embargo some content changes to only appear as published at some future date.

By the same token it also allows for an optional subsequent expiry date. Note: Changes to these dates also constitute modifications to the content and as such
are subject to the same workflow approval processes, where a particular workflow instance is in effect.

The embargo export functionality can also be used independently of any workflow. 