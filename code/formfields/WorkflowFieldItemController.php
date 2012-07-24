<?php
/**
 * Handles individual record data editing or deleting.
 *
 * @package silverstripe-advancedworkflow
 */
class WorkflowFieldItemController extends Controller {

	public static $allowed_actions = array(
		'index',
		'edit',
		'delete',
		'Form'
	);

	protected $parent;
	protected $name;

	public function __construct($parent, $name, $record) {
		$this->parent = $parent;
		$this->name   = $name;
		$this->record = $record;

		parent::__construct();
	}

	public function index() {
		return $this->edit();
	}

	public function edit() {
		return $this->Form()->forTemplate();
	}

	public function Form() {
		$record    = $this->record;
		$fields    = $record->getCMSFields();
		$validator = $record->hasMethod('getValidator') ? $record->getValidator() : null;

		$save = FormAction::create('doSave', 'Save');
		$save->addExtraClass('ss-ui-button ss-ui-action-constructive')
		     ->setAttribute('data-icon', 'accept')
		     ->setUseButtonTag(true);

		$form = new Form($this, 'Form', $fields, new FieldList($save), $validator);
		$form->loadDataFrom($record);
		return $form;
	}

	public function doSave($data, $form) {
		$record = $form->getRecord();

		if(!$record->canEdit()) {
			$this->httpError(403);
		}

		if(!$record->isInDb()) {
			$record->write();
		}

		$form->saveInto($record);
		$record->write();

		return $this->RootField()->forTemplate();
	}

	public function delete($request) {
		if(!SecurityToken::inst()->checkRequest($request)) {
			$this->httpError(400);
		}

		if(!$request->isPOST()) {
			$this->httpError(400);
		}

		if(!$this->record->canDelete()) {
			$this->httpError(403);
		}

		$this->record->delete();
		return $this->RootField()->forTemplate();
	}

	public function RootField() {
		return $this->parent->RootField();
	}

	public function Link($action = null) {
		return Controller::join_links($this->parent->Link(), $this->name, $action);
	}

}