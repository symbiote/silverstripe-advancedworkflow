<?php

namespace Symbiote\AdvancedWorkflow\Admin;

use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;

class WorkflowDefinitionItemRequestClass extends GridFieldDetailForm_ItemRequest
{
    public function updatetemplateversion($data, Form $form, $request)
    {
        $record = $form->getRecord();
        if ($record) {
            $record->updateFromTemplate();
        }
        return $form->loadDataFrom($form->getRecord())->forAjaxTemplate();
    }
}
