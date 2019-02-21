<?php

namespace Symbiote\AdvancedWorkflow\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowDefinition;
use Symbiote\AdvancedWorkflow\Dev\WorkflowBulkLoader;
use Symbiote\AdvancedWorkflow\Forms\GridField\GridFieldExportAction;
use Symbiote\AdvancedWorkflow\Forms\GridField\GridFieldWorkflowRestrictedEditButton;
use Symbiote\AdvancedWorkflow\Services\WorkflowService;

/**
 * @package advancedworkflow
 * @todo UI/UX needs looking at for when current user has no pending and/or submitted items, (Current
 * implementation is bog-standard <p> text)
 */
class AdvancedWorkflowAdmin extends ModelAdmin
{
    private static $menu_title    = 'Workflows';
    private static $menu_priority = -1;
    private static $url_segment   = 'workflows';
    private static $menu_icon_class = 'font-icon-flow-tree';

    /**
     *
     * @var array Allowable actions on this controller.
     */
    private static $allowed_actions = array(
        'export',
        'ImportForm'
    );

    private static $url_handlers = array(
        '$ModelClass/export/$ID!' => 'export',
        '$ModelClass/$Action' => 'handleAction',
        '' => 'index'
    );

    private static $managed_models = WorkflowDefinition::class;

    private static $model_importers = array(
        'WorkflowDefinition' => WorkflowBulkLoader::class
    );

    private static $dependencies = array(
        'workflowService' => '%$' . WorkflowService::class,
    );

    private static $fileEditActions = 'getCMSActions';

    /**
     * Defaults are set in {@link getEditForm()}.
     *
     * @var array
     */
    private static $fieldOverrides = array();

    /**
     * @var WorkflowService
     */
    public $workflowService;

    /**
     * Initialise javascript translation files
     *
     * @return void
     */
    protected function init()
    {
        parent::init();

        Requirements::add_i18n_javascript('symbiote/silverstripe-advancedworkflow:client/lang');
        Requirements::javascript('symbiote/silverstripe-advancedworkflow:client/dist/js/advancedworkflow.js');
        Requirements::css('symbiote/silverstripe-advancedworkflow:client/dist/styles/advancedworkflow.css');
    }

    /*
     * Shows up to x2 GridFields for Pending and Submitted items, dependent upon the current CMS user and
     * that user's permissions on the objects showing in each field.
     */
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $definitionGridFieldName = $this->sanitiseClassName(WorkflowDefinition::class);

        // Show items submitted into a workflow for current user to action
        $fieldName = 'PendingObjects';
        $pending = $this->userObjects(Security::getCurrentUser(), $fieldName);

        if ($this->config()->fieldOverrides) {
            $displayFields = $this->config()->fieldOverrides;
        } else {
            $displayFields = array(
                'Title'          => _t('AdvancedWorkflowAdmin.Title', 'Title'),
                'LastEdited'     => _t('AdvancedWorkflowAdmin.LastEdited', 'Changed'),
                'WorkflowTitle'  => _t('AdvancedWorkflowAdmin.WorkflowTitle', 'Effective workflow'),
                'WorkflowStatus' => _t('AdvancedWorkflowAdmin.WorkflowStatus', 'Current action'),
            );
        }

        // Pending/Submitted items GridField Config
        $config = new GridFieldConfig_Base();
        $config->addComponent(new GridFieldEditButton());
        $config->addComponent(new GridFieldDetailForm());
        $config->getComponentByType(GridFieldPaginator::class)->setItemsPerPage(5);
        $columns = $config->getComponentByType(GridFieldDataColumns::class);
        $columns->setFieldFormatting($this->setFieldFormatting($config));

        if ($pending->count()) {
            $formFieldTop = GridField::create(
                $fieldName,
                $this->isAdminUser(Security::getCurrentUser()) ?
                    _t(
                        'AdvancedWorkflowAdmin.GridFieldTitleAssignedAll',
                        'All pending items'
                    ):
                    _t(
                        'AdvancedWorkflowAdmin.GridFieldTitleAssignedYour',
                        'Your pending items'
                    ),
                $pending,
                $config
            );

            $dataColumns = $formFieldTop->getConfig()->getComponentByType(GridFieldDataColumns::class);
            $dataColumns->setDisplayFields($displayFields);

            $formFieldTop->setForm($form);
            $form->Fields()->insertBefore($definitionGridFieldName, $formFieldTop);
        }

        // Show items submitted into a workflow by current user
        $fieldName = 'SubmittedObjects';
        $submitted = $this->userObjects(Security::getCurrentUser(), $fieldName);
        if ($submitted->count()) {
            $formFieldBottom = GridField::create(
                $fieldName,
                $this->isAdminUser(Security::getCurrentUser()) ?
                    _t(
                        'AdvancedWorkflowAdmin.GridFieldTitleSubmittedAll',
                        'All submitted items'
                    ):
                    _t(
                        'AdvancedWorkflowAdmin.GridFieldTitleSubmittedYour',
                        'Your submitted items'
                    ),
                $submitted,
                $config
            );

            $dataColumns = $formFieldBottom->getConfig()->getComponentByType(GridFieldDataColumns::class);
            $dataColumns->setDisplayFields($displayFields);

            $formFieldBottom->setForm($form);
            $formFieldBottom->getConfig()->removeComponentsByType(GridFieldEditButton::class);
            $formFieldBottom->getConfig()->addComponent(new GridFieldWorkflowRestrictedEditButton());
            $form->Fields()->insertBefore($definitionGridFieldName, $formFieldBottom);
        }

        $grid = $form->Fields()->fieldByName($definitionGridFieldName);
        if ($grid) {
            $grid->getConfig()->getComponentByType(GridFieldDetailForm::class)
                ->setItemEditFormCallback(function ($form) {
                    $record = $form->getRecord();
                    if ($record) {
                        $record->updateAdminActions($form->Actions());
                    }
                });

            $grid->getConfig()->getComponentByType(GridFieldDetailForm::class)
                ->setItemRequestClass(WorkflowDefinitionItemRequestClass::class);
            $grid->getConfig()->addComponent(new GridFieldExportAction());
            $grid->getConfig()->removeComponentsByType(GridFieldExportButton::class);
            $grid->getConfig()->removeComponentsByType(GridFieldImportButton::class);
        }

        $this->extend('updateEditFormAfter', $form);
        
        return $form;
    }

    /*
     * @param Member $user
     * @return boolean
     */
    public function isAdminUser(Member $user)
    {
        if (Permission::checkMember($user, 'ADMIN')) {
            return true;
        }
        return false;
    }

    /*
     * By default, we implement GridField_ColumnProvider to allow users to click through to the PagesAdmin.
     * We would also like a "Quick View", that allows users to quickly make a decision on a given workflow-bound
     * content-object
     */
    public function columns()
    {
        $fields = array(
            'Title' => array(
                'link' => function ($value, $item) {
                    $pageAdminLink = singleton(CMSPageEditController::class)->Link('show');
                    return sprintf('<a href="%s/%s">%s</a>', $pageAdminLink, $item->Link, $value);
                }
            ),
            'WorkflowStatus' => array(
                'text' => function ($value, $item) {
                    return $item->WorkflowCurrentAction;
                }
            )
        );
        return $fields;
    }

    /*
     * Discreet method used by both intro gridfields to format the target object's links and clickable text
     *
     * @param GridFieldConfig $config
     * @return array $fieldFormatting
     */
    public function setFieldFormatting(&$config)
    {
        $fieldFormatting = array();
        // Parse the column information
        foreach ($this->columns() as $source => $info) {
            if (isset($info['link']) && $info['link']) {
                $fieldFormatting[$source] = '<a href=\"$ObjectRecordLink\">$value</a>';
            }
            if (isset($info['text']) && $info['text']) {
                $fieldFormatting[$source] = $info['text'];
            }
        }
        return $fieldFormatting;
    }

    /**
     * Get WorkflowInstance Target objects to show for users in initial gridfield(s)
     *
     * @param Member $member
     * @param string $fieldName The name of the gridfield that determines which dataset to return
     * @return DataList
     * @todo Add the ability to see embargo/expiry dates in report-gridfields at-a-glance if QueuedJobs module installed
     */
    public function userObjects(Member $user, $fieldName)
    {
        $list = new ArrayList();
        $userWorkflowInstances = $this->getFieldDependentData($user, $fieldName);
        foreach ($userWorkflowInstances as $instance) {
            if (!$instance->TargetID || !$instance->DefinitionID) {
                continue;
            }
            // @todo can we use $this->getDefinitionFor() to fetch the "Parent" definition of $instance? Maybe
            // define $this->workflowParent()
            $effectiveWorkflow = DataObject::get_by_id(WorkflowDefinition::class, $instance->DefinitionID);
            $target = $instance->getTarget();
            if (!is_object($effectiveWorkflow) || !$target) {
                continue;
            }
            $instance->setField('WorkflowTitle', $effectiveWorkflow->getField('Title'));
            $instance->setField('WorkflowCurrentAction', $instance->getCurrentAction());
            // Note the order of property-setting here, somehow $instance->Title is overwritten by the Target
            // Title property..
            $instance->setField('Title', $target->getField('Title'));
            $instance->setField('LastEdited', $target->getField('LastEdited'));
            if (method_exists($target, 'CMSEditLink')) {
                $instance->setField('ObjectRecordLink', $target->CMSEditLink());
            }

            $list->push($instance);
        }
        return $list;
    }

    /*
     * Return content-object data depending on which gridfeld is calling for it
     *
     * @param Member $user
     * @param string $fieldName
     */
    public function getFieldDependentData(Member $user, $fieldName)
    {
        if ($fieldName == 'PendingObjects') {
            return $this->getWorkflowService()->userPendingItems($user);
        }
        if ($fieldName == 'SubmittedObjects') {
            return $this->getWorkflowService()->userSubmittedItems($user);
        }
    }

    /**
     * Spits out an exported version of the selected WorkflowDefinition for download.
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function export(HTTPRequest $request)
    {
        $url = explode('/', $request->getURL());
        $definitionID = end($url);
        if ($definitionID && is_numeric($definitionID)) {
            $exporter = new WorkflowDefinitionExporter($definitionID);
            $exportFilename = WorkflowDefinitionExporter::config()
                ->get('export_filename_prefix') . '-' . $definitionID . '.yml';
            $exportBody = $exporter->export();
            $fileData = array(
                'name' => $exportFilename,
                'mime' => 'text/x-yaml',
                'body' => $exportBody,
                'size' => $exporter->getExportSize($exportBody)
            );
            return $exporter->sendFile($fileData);
        }
    }

    /**
     * Required so we can simply change the visible label of the "Import" button and lose some redundant form-fields.
     *
     * @return Form
     */
    public function ImportForm()
    {
        $form = parent::ImportForm();
        if (!$form) {
            return;
        }

        $form->unsetAllActions();
        $newActionList = new FieldList(array(
            new FormAction('import', _t('AdvancedWorkflowAdmin.IMPORT', 'Import workflow'))
        ));
        $form->Fields()->fieldByName('_CsvFile')->getValidator()->setAllowedExtensions(array('yml', 'yaml'));
        $form->Fields()->removeByName('EmptyBeforeImport');
        $form->setActions($newActionList);

        return $form;
    }

    /**
     * @param WorkflowService $workflowService
     * @return $this
     */
    public function setWorkflowService(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
        return $this;
    }

    /**
     * @return WorkflowService
     */
    public function getWorkflowService()
    {
        return $this->workflowService;
    }
}
