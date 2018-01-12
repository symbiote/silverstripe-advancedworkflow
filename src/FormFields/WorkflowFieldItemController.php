<?php

namespace Symbiote\AdvancedWorkflow\FormFields;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\SecurityToken;

/**
 * Handles individual record data editing or deleting.
 *
 * @package silverstripe-advancedworkflow
 */
class WorkflowFieldItemController extends Controller
{
    private static $allowed_actions = array(
        'index',
        'edit',
        'delete',
        'Form'
    );

    protected $parent;
    protected $name;

    public function __construct($parent, $name, $record)
    {
        $this->parent = $parent;
        $this->name   = $name;
        $this->record = $record;

        parent::__construct();
    }

    public function index()
    {
        return $this->edit();
    }

    public function edit()
    {
        return $this->Form()->forTemplate();
    }

    public function Form()
    {
        $record    = $this->record;
        $fields    = $record->getCMSFields();
        $validator = $record->hasMethod('getValidator') ? $record->getValidator() : null;

        $save = FormAction::create('doSave', _t('WorkflowReminderTask.SAVE', 'Save'));
        $save->addExtraClass('btn btn-primary font-icon-save')
             ->setUseButtonTag(true);

        /** @skipUpgrade */
        $form = Form::create($this, 'Form', $fields, FieldList::create($save), $validator);
        if ($record && $record instanceof DataObject && $record->exists()) {
            $form->loadDataFrom($record);
        }
        return $form;
    }

    public function doSave($data, $form)
    {
        $record = $form->getRecord();

        if (!$record || !$record->exists()) {
            $record = $this->record;
        }

        if (!$record->canEdit()) {
            $this->httpError(403);
        }

        if (!$record->isInDb()) {
            $record->write();
        }

        $form->saveInto($record);
        $record->write();

        return $this->RootField()->forTemplate();
    }

    public function delete($request)
    {
        if (!SecurityToken::inst()->checkRequest($request)) {
            $this->httpError(400);
        }

        if (!$request->isPOST()) {
            $this->httpError(400);
        }

        if (!$this->record->canDelete()) {
            $this->httpError(403);
        }

        $this->record->delete();
        return $this->RootField()->forTemplate();
    }

    public function RootField()
    {
        return $this->parent->RootField();
    }

    public function Link($action = null)
    {
        return Controller::join_links($this->parent->Link(), $this->name, $action);
    }
}
