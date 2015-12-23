<?php

/**
 * 
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class SetPropertyWorkflowAction extends WorkflowAction {
	private static $db = array(
		'Property'	=> 'Varchar',
		'Value'		=> 'Text',
	);
	
	public function execute(WorkflowInstance $workflow) {
		if (!$target = $workflow->getTarget()) {
			return true;
		}

		if ($target->hasField($this->Property)) {
			$target->setField($this->Property, $this->Value);
		}

		$target->write();

		return true;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->addFieldsToTab('Root.Main', array(
			TextField::create('Property', _t('SetPropertyWorkflowAction.PROPERTY', 'Property'))
				->setRightTitle(_t('SetPropertyWorkflowAction.PROPERTYTITLE', 'Property to set; if this exists as a setter method, will be called passing the value')),
			TextField::create('Value', 'Value')
		));

		return $fields;
	}

}
