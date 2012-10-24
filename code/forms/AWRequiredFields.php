<?php
/**
 * Extends RequiredFields so we can prevent DO writes in AW's controller(s) without needing to catch Exceptions from DO->validate() all over the place.
 * Note specifically $this->getExtendedValidationRoutines() - anti-pattern anyone?
 *
 * @author Russell Michell russell@silverstripe.com
 * @package advancedworkflow
 */
class AWRequiredFields extends RequiredFields {

	protected $data = array();
	protected static $caller;

	public function php($data) {
		$valid = parent::php($data);
		$this->setData($data);

		// Fetch any extended validation routines on the caller
		$extended = $this->getExtendedValidationRoutines();

		// Only deal-to extended routines once the parent is done
		if($valid && $extended['fieldValid'] !== true) {
			$fieldName = $extended['fieldName'];
			$formField = $extended['fieldField'];
			$errorMessage = sprintf($extended['fieldMsg'],
				strip_tags('"'.(($formField && $formField->Title()) ? $formField->Title() : $fieldName).'"'));

			if($formField && $msg = $formField->getCustomValidationMessage()) {
				$errorMessage = $msg;
			}
			
			$this->validationError(
				$fieldName,
				$errorMessage,
				"required"
			);
			$valid = false;
		}
		return $valid;
	}

	/*
	 * Allows for the addition of an arbitrary no. additional, dedicated and "extended" validation methods on classes that call AWRequiredFields.
	 * To add specific validation methods to a caller:
	 *
	 * 1). Write each checking method using this naming prototype: public function extendedRequiredFieldsXXX(). All methods so named will be called.
	 * 2). Call AWRequiredFields->setCaller($this)
	 *
	 * Each extended method thus called, should return an array of a specific format. (See: static $extendedMethodReturn on the caller)
	 *
	 * @return array $return
	 */
	public function getExtendedValidationRoutines() {
		// Setup a return array 
		$return = array(
			'fieldValid'=>true,
			'fieldName'	=>null,
			'fieldField'=>null,
			'fieldMsg'	=>null
		);
		$caller = $this->getCaller();
		$methods = get_class_methods($caller);
		if(!$methods) {
			return $return;
		}
		foreach($methods as $method) {
			if(!preg_match("#extendedRequiredFields#",$method)) {
				continue;
			}
			// One of the DO's validation methods has failed
			$extended = $caller->$method($this->getData());
			if($extended['fieldValid'] !== true) {
				$return['fieldValid']	= $extended['fieldValid'];
				$return['fieldName']	= $extended['fieldName'];
				$return['fieldField']	= $extended['fieldField'];
				$return['fieldMsg']		= $extended['fieldMsg'];
				break;
			}
		}
		return $return;
	}

	protected function setData($data) {
		$this->data = $data;
	}

	protected function getData() {
		return $this->data;
	}

	public function setCaller($caller) {
		self::$caller = $caller;
	}

	public function getCaller() {
		return self::$caller;
	}
}