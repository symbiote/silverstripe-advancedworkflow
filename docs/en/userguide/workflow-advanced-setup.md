---
title: Workflow advanced setup
---

# Advanced Transitions

Sometimes an action will have a single transition from itself to the next action; when the workflow begins execution,
these actions are executed immediately one after another. An example of this might be when you want to assign a user to the workflow,
then notify them immediately; the "Assign" action will have a single transition to the "Notify" action.

If you want the user to make an explicit choice about which path of the workflow to move to after a certain action,
there should be multiple transitions created going out from that action. Continuing the above flow; after Notifying users,
you might want them to make a decision as to whether to "Approve" or "Reject" the item; therefore, from the Notify action, there may be two transitions:

1. The Approve transition that leads to the approval and publication actions.
2. The Reject transition that leads to the cancel action.

The name given to a transition appears on the "Workflow Actions" tab of a content item when a content author needs to make a decision.

Each action may have an arbitrary number of outbound transitions, and transitions can loop around back to earlier parts of the workflow too!

## Customising Actions and Transitions

Initially we created a two-step workflow from a "workflow template".
The workflow system is very powerful, so we'll run you through
creating the same setup manually. That's a good way to learn the nuts
and bolts of how things fit together, and will enable you to
customize your workflow to your needs.

We assume you've got users and groups set up already.
Now, switch to the "Workflow" section in the CMS menu. Create a new workflow there
through the "Add Workflow Definition" button. Call it "Two-step workflow" for now. After saving the item, assign it to the "Editors" group

**Actions**

1. You should have noticed a new item appear in the main CMS content-area entitled; "Workflow". 
2. From its drop-down menu, select the "Assign Users To Workflow Action" option and then select the "Create" button.
3. In the popup dialogue that appears, type the title: "Apply for approval" (Note: the first workflow action's title is used for the apply button's label in the CMS UI).
4. Leave everything else as-is, but in the "Groups" drop-down menu - locate and select the "Editors" group, then select "Save".
5. Create another action, this time selecting the "Notify Users Workflow Action" option. Call it "Notify Editors". Leave everything-else as-is, but because this action sends emails, enter an email subject, a from-address and email-body template (see the "Formatting Help" menu at the bottom of the popup dialogue), then click "Save".
6. Create another action and select the "Simple Approval Workflow Action" option, select the "Create" button and in the popup, give it the title "Editor Approval", then select "Save". Note: in a simple workflow such as this, the approval action doesn't actually do anything. However, it's good practice to have it as it provides a clear reference point of where approval occurs. In a more complex workflow, you might use a "Counting Approval" action to do things like count the number of people who have approved.
7. Create another action "Assign Users To Workflow Action", and entitle this one "Assign Managers". Leave everything else as-is and locate and select the "Managers" group from the "Groups" drop-down, then select "Save".
8. Create another action "Notify Users Workflow Action", and name it "Notify Managers". Leave everything-else as-is, but like the previous "Notify Users Workflow Action", enter the email-specific details, then select "Save".
9. Create another action "Simple Approval Workflow Action" and name it "Manager Approval", selecting "Save" when you’re done.
10. Create another action "Publish Item Workflow Action" and call it "Publish item", leave everything else as-is and select "Save" when you’re done.
11. Create another action "Cancel Workflow Action", call it "Cancel", leave everything else as-is, and select "Save" when you’re done.

Okay, now we need to join up these actions using transitions, so that users can make the appropriate choices.

**Transitions**

* On your "Apply for approval" action in the list of actions you just created in the above step, select the "Add Transition" button and in the popup dialogue that appears, entitle the transition "Send Notification", leave everything else as-is and select "Notify Editors" as the "Next Action", then select the "Save" button.
* On your "Notify Editors" action, select the "Add Transition button and call this one "Wait for approval" and select "Editor Approval" as the "Next Action", then select the "Save" button.
* On your "Editor Approval" action, select the "Add Transition" button and call this one "Approve", select "Assign Managers" as the "Next Action".
* Create another transition on the "Editor Approval" action, call this one "Reject" and select "Cancel" as the "Next Action", then select the "Save" button - you've just created your first decision point.
* On the "Assign Managers" action, add a transition and call it "Notify Managers", select "Notify Managers" as the next action, then select "Save".
* On the "Notify Managers" action, add a transition and call it "Wait for approval" and select "Manager Approval" as the next action, then select "Save".
* On the "Manager Approval" action, add a transition and call it "Accept and Publish" and select "Publish item" as the next action.
* Create another transition on the "Manager Approval" action and call it "Reject and Cancel", then select "Cancel" as the next action, then select "Save".
* Select the "Save" button at the bottom of the screen to finalize your workflow, and you're done.
