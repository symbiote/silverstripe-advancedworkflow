<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * Interface used to manage workflow schemas
 *
 * @author marcus@silverstripe.com.au
 */
class WorkflowAdminController extends LeftAndMain {
	public static $managed_models = array('WorkflowDefinition');
	public static $url_segment = 'workflowadmin';
	public static $menu_title = 'Workflow';

	public static $url_rule = '$Action//$ID';

	public static $tree_class = 'WorkflowDefinition';

	public static $allowed_actions = array(
		'loadworkflow',
		'EditForm',
		'save',
		'delete',
		'CreateWorkflowForm',
		'createworkflow',
		'deleteworkflows',
		'DeleteItemsForm'
	);

	/**
	 * Include required JS stuff
	 */
	public function init() {
		parent::init();
		Requirements::css('ssau-formfields/javascript/jstree-0.9.9a2/themes/default/style.css');
		Requirements::javascript('ssau-formfields/javascript/jstree-0.9.9a2/jquery.tree.js');
		Requirements::javascript('activityworkflow/javascript/WorkflowAdmin.jquery.js');
	}

	public function EditForm($request=null, $vars=null) {
		$forUser = Member::currentUser();
		$id = (int) $this->request->postVar('ID');
		if (!$id) {
			$id = $this->request->param('ID');
		}
		$type = $this->request->postVar('ClassType');
		if (!$type) {
			$type = $this->request->getVar('ClassType');
			if (!$type) {
				$type = 'WorkflowDefinition';
			}
		}

		$editItem = null;
		if ($id) {
			$editItem = DataObject::get_by_id($type, $id);
		} else {
			
		}

		$form = null;

		if ($editItem) {
			$idField = new HiddenField('ID', '', $editItem->ID);
			$typeField = new HiddenField('ClassType', '', $editItem->ClassName);
			
			$fields = $editItem->getCMSFields();
			$fields->push($idField);
			$fields->push($typeField);

			$actions = new FieldSet();
			$actions->push(new FormAction('save', _t('ActivityWorkflow.SAVE', 'Save')));
			$form = new Form($this, "EditForm", $fields, $actions);
			$form->loadDataFrom($editItem);
		}

		return $form;
	}

	public function save($data, Form $form, $request) {
		$type = isset($data['ClassType']) ? $data['ClassType'] : 'WorkflowDefinition';
		$id = $data['ID'];

		$editItem = DataObject::get_by_id($type, $id);

		if ($editItem) {
			$form->saveInto($editItem);
			$editItem->write();
			FormResponse::status_message("Saved", "good");
		} else {
			FormResponse::status_message("Invalid object", "bad");
		}
		return FormResponse::respond();
	}


	/**
	 * Gets the changes for a particular user
	 */
	public function loadworkflow() {
		return $this->renderWith('WorkflowAdminController_right');
	}

	/**
	 * For now just returning all workflows. 
	 *
	 * @return DataObjectSet
	 */
	public function Workflows() {
		return DataObject::get('WorkflowDefinition');
	}

	/**
	 * Get the form used to create a new workflow
	 *
	 * @return Form
	 */
	public function CreateWorkflowForm() {
		$classes = ClassInfo::subclassesFor(self::$tree_class);
		array_unshift($classes, '');
		
		$actionclasses = ClassInfo::subclassesFor('WorkflowAction');
		array_shift($actionclasses);
		array_unshift($actionclasses, '');

		$transitionclasses = ClassInfo::subclassesFor('WorkflowTransition');
		array_unshift($transitionclasses, '');

		$fields = new FieldSet(
			new HiddenField("ParentID"),
			new HiddenField("ParentType"),
			new HiddenField("CreateType"),
			new HiddenField("Locale", 'Locale', Translatable::get_current_locale()),
			new DropdownField("WorkflowDefinitionTypes", "", $classes),
			new DropdownField("WorkflowActionTypes", "", $actionclasses),
			new DropdownField("WorkflowTransitionTypes", "", $transitionclasses)
		);

		$actions = new FieldSet(
			new FormAction("createworkflowitem", _t('WorkflowAdmin.CREATE',"Create"))
		);

		return new Form($this, "CreateWorkflowForm", $fields, $actions);
	}

	/**
	 * Create a new workflow
	 */
	public function createworkflowitem($data, $form, $request) {

		return print_r($data, true);
	}

	/**
	 * Copied from AssetAdmin...
	 *
	 * @return Form
	 */
	function DeleteItemsForm() {
		$form = new Form(
			$this,
			'DeleteItemsForm',
			new FieldSet(
				new LiteralField('SelectedPagesNote',
					sprintf('<p>%s</p>', _t('WorkflowAdmin.SELECT_WORKFLOWS','Select the workflows that you want to delete and then click the button below'))
				),
				new HiddenField('csvIDs')
			),
			new FieldSet(
				new FormAction('deleteworkflows', _t('WorkflowAdmin.DELWORKFLOWS','Delete the selected workflows'))
			)
		);

		$form->addExtraClass('actionparams');

		return $form;
	}

	public function deleteworkflows() {
		$script = '';
		$ids = split(' *, *', $_REQUEST['csvIDs']);
		$script = '';

		if(!$ids) return false;

		foreach($ids as $id) {
			if(is_numeric($id)) {
				$record = DataObject::get_by_id('WorkflowDefinition', $id);
				if($record) {
					$script .= $this->deleteTreeNodeJS($record);
					$record->delete();
					$record->destroy();
				}
			}
		}

		$size = sizeof($ids);
		if($size > 1) {
		  $message = $size.' '._t('WorkflowAdmin.WORKFLOWS_DELETED', 'workflows deleted.');
		} else {
		  $message = $size.' '._t('WorkflowAdmin.WORKFLOW_DELETED', 'workflow deleted.');
		}

		$script .= "statusMessage('$message');";
		echo $script;
	}
}