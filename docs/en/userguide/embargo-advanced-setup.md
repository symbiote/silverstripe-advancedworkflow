---
title: Embargo advanced setup
---

## Advanced Embargo and Expirty

### Publish target by version
The `WorkflowPublishTargetJob` supports publishing a specific (approved) version of a DataObject, however, it requires
some extra steps on our part.

`Versioned::publish()` supports the publication of a specific version number to a specific stage, however, in
`SiteTree:doPublish()` we can see that the usage of `$this->publish(..)` is hard coded to always publish from "Stage" to
"Live".

To get around this, you will need to do two things.

#### First
You will need to implement a new method onto your Versioned DataObjects called `doVersionPublish`. This method, at the
very least, will need to call `publish()`, but you could do anything/everything else from `doPublish()` as well.

Where you implement this method is up to you. In the example below, we've added `doVersionPublish` to a `DataExtension`
which has been applied to `SiteTree`:

	public function doVersionPublish()
    {
        if (!$this->owner->canPublish()) {
            return false;
        }

        $stage = 'Stage';
        if ($this->owner->hasField('Version')) {
            $stage = $this->owner->getField('Version');
        }

        // Handle activities undertaken by extensions
        $this->owner->invokeWithExtensions('onBeforePublish', $original);
        $this->owner->write();
        $this->owner->publish($stage, "Live");

        // Handle activities undertaken by extensions
        $this->owner->invokeWithExtensions('onAfterPublish', $original);

        return true;
    }

Our `publish()` method now looks for a field called `Version` on our DataObject, and if it's available, it uses that as
the Stage. Which brings us to,

#### Second
Your Versioned DataObjects will need to have an available database field called `Version` (by default, anything
extending `SiteTree` will have this field). This value is passed to the `WorkflowPublishTargetJob` **at the time of
approval**. `WorkflowPublishTargetJob::process` will then retrieve **this** version of your DataObject, and call
`doVersionPublish` on that DataObject.

Because `doVersionPublish` is called on that specific version of your DataObject, it will know what version number it
is, and when `publish($stage, "Live")` is called, it will publish that specific version.
