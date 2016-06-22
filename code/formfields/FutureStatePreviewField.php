<?php

use SilverStripe\Model\FieldType\DBField;

/**
 * A form field that allows easy accessibility to view the Future state of a page, or possibly object.
 *
 * @package advancedworkflow
 */
class FutureStatePreviewField extends DatetimeField
{

    public function __construct($name, $title = null, $value = "")
    {
        parent::__construct($name, $title, $value);
        $this->dateField
            ->setName('')
            ->addExtraClass('workflow-future-preview-datetime');
        $this->timeField
            ->setName('')
            ->addExtraClass('workflow-future-preview-datetime');
    }

    /**
     * @param array $properties
     * @return HTMLText
     */
    public function FieldHolder($properties = array())
    {
        Requirements::javascript(ADVANCED_WORKFLOW_DIR . '/javascript/FutureStatePreviewField.js');

        $this->addExtraClass('datetime');
        return parent::FieldHolder($properties);
    }

    /**
     * do not need to make this readonly
     * @return $this
     */
    public function performReadonlyTransformation()
    {
        return $this;
    }

    /**
     * For action label
     */
    public function PreviewActionTitle()
    {
        return Convert::raw2xml(
            _t('WorkflowEmbargoExpiryExtension.FUTURE_PREVIEW_ACTION', 'View in new window')
        );
    }

}
