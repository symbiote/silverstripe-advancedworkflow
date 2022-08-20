---
title: Importing and exporting workflows
---
# Importing and exporting workflows

In the Workflow CMS left-hand navigation menu item, authorised users are able to export a WorkflowDefinition from one
SilverStripe installation, and import it into another.

## Export a workflow
From the table of workflows in the Workflow CMS admin-area, select the 'download' icon located to the right of the 'edit' and 'delete' icons.

You should be prompted to download a file which you should save to your local computer.

## Import a workflow

Simply login to another SilverStripe installation and navigate to the Workflow Admin of that CMS. You should see an
'Import' heading at the bottom of the central CMS pane with a 'Browse' button below that.

Select the 'Browse' button and locate the downloaded file in your computer's file-browser that will automatically appear, then select the
'Import Definition' button. That's it!

# Exported related user and group data

Because users and groups can be related to Workflow Actions and Transitions, these associations are also exported.
However, these relations will only be made at the import stage if the same Groups and/or Users also exist in the target
CMS. If the latter is not the case, you will need to manually re-create the users and groups and re-assign them to the
imported workflow.
