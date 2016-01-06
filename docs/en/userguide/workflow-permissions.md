# Workflow permissions

### Adding Groups and Users

First of all, login to the CMS as an administrator. Ensure you have "Authors", "Editors" and "Managers" groups created with a single user linked to each group (in the standard "Security" admin). Assign the five "Advanced Workflow" permissions to each group.

For more details about permissions, see the "Workflow permissions" section below.


This section describes the different permission that can be assigned to a user, group or role.

![Advanced workflow permissions](_images/permissions.png)

## Apply workflow

A user with this permission can choose which workflow that should be used for an item. E.g. for a page this permission
will allow the user to change workflow in a drop down under the "Page > Settings > Workflow" tab.

## Create workflow

A user with this permission can create and change workflow definitions.

## Delete workflow

A user with this permission can delete:

 * workflow definitions
 * workflow instances

I.e. if a user needs to completely stop and delete an active workflow, they would need this permission.

## Reassign active workflows

A user with this permission can reassign active workflows to different users and groups.

## View active workflows

A user with this permission can view active workflows via the workflows admin panel.