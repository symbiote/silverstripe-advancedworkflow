<?php

/**
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
			$target->setField($this->Property, $this->resolveValue($this->Value));
		}

		$target->write();

		return true;
	}

    protected function resolveValue($value) {
        if (strpos($value, 'strtotime') !== false) {
            if (preg_match('/strtotime\((.*?)\)/', $value, $matches)) {
                $timepart = $matches[1];
                $value = str_replace($matches[0], strtotime($timepart), $value);
            }
        }

        if (strpos($value, 'date') !== false) {
            $find = $format = $tstamp = false;
            if (preg_match('/date\((.*?),\s*(.*?)\)/', $value, $matches)) {
                $find = $matches[0];
                $format = $matches[1];
                $tstamp = $matches[2];
            } else if (preg_match('/date\((.*?)\)/', $value, $matches)) {
                $find = $matches[0];
                $format = $matches[1];
                $tstamp = time();
            }
            if ($find) {
                $value = str_replace($find, date($format, $tstamp), $value);
            }
        }

        return $value;
    }

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->addFieldsToTab('Root.Main', array(
			TextField::create('Property', _t('SetPropertyWorkflowAction.PROPERTY', 'Property'))
				->setRightTitle(_t('SetPropertyWorkflowAction.PROPERTYTITLE', 'Property to set; if this exists as a setter method, will be called passing the value')),
			TextField::create('Value', _t('SetPropertyWorkflowAction.VALUE', 'Value'))
                ->setRightTitle(_t('SetPropertyWorkflowAction.VALUETITLE',
                    'Value to set; you can use date(Y-m-d formatting, [timestamp]), and strtotime(str) as values; mixable too, eg date(Y-m-d H:i:s, strtotime(+1 week))'))
            
		));

		return $fields;
	}

}
