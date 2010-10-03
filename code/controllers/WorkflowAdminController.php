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
		'deleteworkflow',
		'sort',
	);

	/**
	 * Include required JS stuff
	 */
	public function init() {
		parent::init();
		Requirements::css('ssau-formfields/javascript/jstree-0.9.9a2/themes/default/style.css');
		Requirements::javascript('ssau-formfields/javascript/jstree-0.9.9a2/jquery.tree.js');
		Requirements::javascript('sapphire/thirdparty/jquery-form/jquery.form.js');
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
		if ($id && is_numeric($id)) {
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
		return singleton('WorkflowService')->getDefinitions();
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
		$newItem = null;
		
		if ($data['WorkflowDefinitionTypes']) {
			$createType = $data['WorkflowDefinitionTypes'];
			$newItem = new WorkflowDefinition();
			$newItem->Title = 'New Workflow Definition';
			$newItem->write();
		} else {
			$actionclasses = ClassInfo::subclassesFor('WorkflowAction');
			$transitionclasses = ClassInfo::subclassesFor('WorkflowTransition');
			$createType = $data['CreateType'];
			
			$idField = null;
			if (in_array($createType, $actionclasses)) {
				$newItem = new $createType;
				$idField = 'WorkflowDefID';
			} else if (in_array($createType, $transitionclasses)) {
				$newItem = new $createType;
				$idField = 'ActionID';
			}

			if (!$newItem) {
				throw new Exception("Invalid creation type");
			}

			$newItem->Title = 'New '.$createType;
			$newItem->$idField = $data['ParentID'];

			$newItem->write();
		}

		$res = array(
			'success' => 1,
			'type' => $newItem->ClassName,
			'ID' => $newItem->ID
		);

		return Convert::array2json($res);
	}

	public function deleteworkflow($request) {
		$id = (int) $request->postVar('ID');
		$type = $request->postVar('Type');
		if (!ClassInfo::exists($type) || !$id) {
			throw new Exception("Invalid type passed");
		}
		if($id) {
			$record = DataObject::get_by_id($type, $id);
			if($record) {
				$record->delete();
				$record->destroy();
			}
		}
		return Convert::array2json(array('success' => 1));
	}

	public function sort($request) {
		$sortIds = $request->postVar('ids');
		$ids = explode(',', trim($sortIds, ','));
		if (!count($ids)) {
			return '{}';
		}

		$bits = explode('-', $ids[0]);
		$item = DataObject::get_by_id($bits[0], $bits[1]);
		$currentObjects = null;
		if ($item instanceof WorkflowAction) {
			$parent = $item->WorkflowDef();
			$currentObjects = $parent->getSortedActions();
		} else if ($item instanceof WorkflowTransition) {
			$parent = $item->Action();
			$currentObjects = $parent->Transitions();
		} else if ($item instanceof WorkflowDefinition) {
			$currentObjects = DataObject::get('WorkflowDefinition', '', 'Sort ASC');
		} else {
			throw new Exception("Invalid sort options");
		}

		$newOrder = array();
		foreach ($ids as $id) {
			$bits = explode('-', $id);
			$newOrder[] = $bits[1];
		}

		if (count($newOrder) != $currentObjects->Count()) {
			throw new Exception("Invalid ordering count");
		}

		singleton('WorkflowService')->reorder($currentObjects, $newOrder);

		return '{}';
	}
}