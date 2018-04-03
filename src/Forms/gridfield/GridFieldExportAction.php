<?php

namespace Symbiote\AdvancedWorkflow\Forms\GridField;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;
use Symbiote\AdvancedWorkflow\Admin\AdvancedWorkflowAdmin;

/**
 * This class is a {@link GridField} component that adds an export action for
 * WorkflowDefinition objects shown in GridFields.
 *
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class GridFieldExportAction implements GridField_ColumnProvider, GridField_ActionProvider
{
    /**
     * Add a column 'Delete'
     *
     * @param GridField $gridField
     * @param array $columns
     */
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns)) {
            $columns[] = 'Actions';
        }
    }

    /**
     * Return any special attributes that will be used for FormField::create_tag()
     *
     * @param GridField $gridField
     * @param DataObject $record
     * @param string $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return array('class' => 'grid-field__col-compact');
    }

    /**
     * Add the title
     *
     * @param GridField $gridField
     * @param string $columnName
     * @return array
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName == 'Actions') {
            return array('title' => '');
        }
    }

    /**
     * Which columns are handled by this component
     *
     * @param type $gridField
     * @return type
     */
    public function getColumnsHandled($gridField)
    {
        return array('Actions');
    }

    /**
     * Which GridField actions are this component handling
     *
     * @param GridField $gridField
     * @return array
     */
    public function getActions($gridField)
    {
        return array('exportrecord');
    }

    /**
     *
     * @param GridField $gridField
     * @param DataObject $record
     * @param string $columnName
     * @return string - the HTML for the column
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        // Disable the export icon if current user doesn't have access to view CMS Security settings
        if (!Permission::check('CMS_ACCESS_SecurityAdmin')) {
            return '';
        }

        $field = GridField_FormAction::create(
            $gridField,
            'ExportRecord' . $record->ID,
            false,
            "exportrecord",
            array('RecordID' => $record->ID)
        )
            ->addExtraClass('btn btn--no-text btn--icon-md font-icon-export');

        $segment1 = Director::baseURL();
        $segment2 = Config::inst()->get(AdvancedWorkflowAdmin::class, 'url_segment');
        $segment3 = str_replace('\\', '-', $record->getClassName());
        $data = new ArrayData(array(
            'Link' => Controller::join_links($segment1, 'admin', $segment2, $segment3, 'export', $record->ID),
            'ExtraClass' => $field->extraClass(),
        ));

        $template = SSViewer::get_templates_by_class($this, '', __CLASS__);
        return $data->renderWith($template);
    }

    /**
     * Handle the actions and apply any changes to the GridField
     *
     * @param GridField $gridField
     * @param string $actionName
     * @param mixed $arguments
     * @param array $data - form data
     * @return void
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
    }
}
