<?php
/**
 * An admin interface for managing workflow definitions, actions and transitions.
 *
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage admin
 */
class AdvancedWorkflowAdmin extends ModelAdmin {

	public static $title = 'Workflows';
	public static $menu_title = 'Workflows';
	public static $url_segment = 'workflows';

	public static $managed_models = array(
		'WorkflowDefinition' => array('record_controller' => 'AdvancedWorkflowAdmin_RecordController'),
		'WorkflowAction'     => array('record_controller' => 'AdvancedWorkflowAdmin_RecordController'),
		'WorkflowTransition' => array('record_controller' => 'AdvancedWorkflowAdmin_RecordController')
	);

	public static $allowed_actions = array(
		'tree',
		'sort',
		'CreateDefinitionForm',
		'CreateActionForm',
		'CreateTransitionForm'
	);
	
	/**
	 * @return string
	 */
	public function tree($request) {
		$data     = array();
		$class    = $request->getVar('class');
		$id       = $request->getVar('id');

		if($id == 0) {
			$items = singleton('WorkflowService')->getDefinitions();
			$type  = 'WorkflowDefinition';
		} elseif($class == 'WorkflowDefinition') {
			$items = DataObject::get('WorkflowAction', '"WorkflowDefID" = ' . (int) $id);
			$type  = 'WorkflowAction';
		} else {
			$items   = DataObject::get('WorkflowTransition', '"ActionID" = ' . (int) $id);
			$type    = 'WorkflowTransition';
		}

		if($items) foreach($items as $item) {
			$new = array(
				'data' => array(
					'title' => $item->Title,
					'attr'  => array('href' => $this->Link("$type/{$item->ID}/edit")),
					'icon'  => $item->stat('icon')),
				'attr' => array(
					'id'         => "{$type}_{$item->ID}",
					'title'      => Convert::raw2att($item->Title),
					'data-id'    => $item->ID,
					'data-type'  => $type,
					'data-class' => $item->class)
			);

			if($item->numChildren() > 0) {
				$new['state'] = 'closed';
			}

			$data[] = $new;
		}

		return Convert::raw2json($data);
	}

	/**
	 * @return string
	 */
	public function sort($request) {
		$service  = singleton('WorkflowService');
		$type     = $request->postVar('type');
		$order    = $request->postVar('ids');
		$parentId = $request->postVar('parent_id');

		switch($type) {
			case 'WorkflowDefinition':
				$current = $service->getDefinitions();
				break;
			case 'WorkflowAction':
				$current = DataObject::get('WorkflowAction', sprintf('"WorkflowDefID" = %d', $parentId));
				break;
			case 'WorkflowTransition':
				$current = DataObject::get('WorkflowTransition', sprintf('"ActionID" = %d', $parentId));
				break;
			default:
				return $this->httpError(400, _t('AdvancedWorkflowAdmin.INVALIDSORTTYPE', 'Invalid sort type.'));
		}

		if(!$order || count($order) != count($current)) {
			return new SS_HTTPResponse(
				null, 400, _t('AdvancedWorkflowAdmin.INVALIDSORT', 'An invalid sort order was specified.')
			);
		}

		$service->reorder($current, $order);

		return new SS_HTTPResponse(
			null, 200, _t('AdvancedWorkflowAdmin.SORTORDERSAVED', 'The sort order has been saved.')
		);
	}

	/**
	 * @return Form
	 */
	public function CreateDefinitionForm() {
		return new Form(
			$this,
			'CreateDefinitionForm',
			new FieldSet(
				$this->getClassCreationField('WorkflowDefinition')),
			new FieldSet(
				new FormAction('doCreateWorkflowItem', _t('AdvancedWorkflowAdmin.CREATE', 'Create')))
		);
	}

	/**
	 * @return Form
	 */
	public function CreateActionForm() {
		return new Form(
			$this,
			'CreateActionForm',
			new FieldSet(
				$this->getClassCreationField('WorkflowAction', false),
				new HiddenField('ParentID')),
			new FieldSet(
				new FormAction('doCreateWorkflowItem', _t('AdvancedWorkflowAdmin.CREATE', 'Create')))
		);
	}

	/**
	 * @return Form
	 */
	public function CreateTransitionForm() {
		return new Form(
			$this,
			'CreateTransitionForm',
			new FieldSet(
				$this->getClassCreationField('WorkflowTransition'),
				new HiddenField('ParentID')),
			new FieldSet(
				new FormAction('doCreateWorkflowItem', _t('AdvancedWorkflowAdmin.CREATE', 'Create')))
		);
	}

	/**
	 * Creates a workflow item - a definition, action, transition or any subclasses
	 * of these.
	 *
	 * @param  array $data
	 * @param  Form $form
	 * @return string
	 */
	public function doCreateWorkflowItem($data, $form) {
		// assume the form name is in the form CreateTypeForm
		$data      = $form->getData();
		$type      = 'Workflow' . substr($form->Name(), 6, -4);
		$allowSelf = ($type != 'WorkflowAction');

		// determine the class to create - if it is manually specified then use that,
		// falling back to creating an object of the root type if allowed.
		if(isset($data['Class']) && class_exists($data['Class'])) {
			$class = $data['Class'];
			$valid = is_subclass_of($class, $type) || ($allowSelf && $class == $type);

			if(!$valid) return new SS_HTTPResponse(
				null, 400, _t('AdvancedWorkflowAdmin.INVALIDITEM', 'An invalid workflow item was specified.')
			);
		} else {
			$class = $type;

			if(!$allowSelf) return new SS_HTTPResponse(
				null, 400, _t('AdvancedWorkflowAdmin.MUSTSPECIFYITEM', 'You must specify a workflow item to create.')
			);
		}

		// check that workflow actions and transitions have valid parent id values.
		if($type != 'WorkflowDefinition') {
			$parentId = $data['ParentID'];
			$parentClass = ($type == 'WorkflowAction') ? 'WorkflowDefinition' : 'WorkflowAction';

			if(!is_numeric($parentId) || !DataObject::get_by_id($parentClass, $parentId)) {
				return new SS_HTTPResponse(
					null, 400, _t('AdvancedWorkflowAdmin.INVALIDPARENT', 'An invalid parent was specified.')
				);
			}
		}

		// if an add form can be returned without writing a new rcord to the database,
		// then just do that
		if(array_key_exists($class, $this->getManagedModels())) {
			$form  = $this->$type()->AddForm();
			$title = singleton($type)->singular_name();

			if($type == 'WorkflowTransition') {
				$form->dataFieldByName('ActionID')->setValue($parentId);
			}
		} else {
			$record = new $class;
			$record->Title = sprintf(_t('AdvancedWorkflowAdmin.NEWITEM', 'New %s'), $record->singular_name());

			if($type == 'WorkflowAction') {
				$record->WorkflowDefID = $parentId;
			} elseif($type == 'WorkflowTransition') {
				$record->ActionID = $parentId;
			}

			$record->write();

			$control = $this->getRecordControllerClass('WorkflowDefinition');
			$control = new $control($this->$type(), null, $record->ID);
			$form    = $control->EditForm();
			$title   = $record->singular_name();
		}

		return new SS_HTTPResponse(
			$this->isAjax() ? $form->forAjaxTemplate() : $form->forTemplate(), 200,
			sprintf(_t('AdvancedWorkflowAdmin.CREATEITEM', 'Fill out this form to create a "%s".'), $title)
		);
	}

	/**
	 * Returns a dropdown createable classes which all have a common parent class,
	 * or a label field if only one option is available.
	 *
	 * @param  string $class The parent class.
	 * @param  bool $includeSelf Include the parent class itself.
	 * @return DropdownField|LabelField
	 */
	protected function getClassCreationField($class, $includeSelf = true) {
		$classes    = ClassInfo::subclassesFor($class);
		$createable = array();

		if(!$includeSelf) {
			array_shift($classes);
		}

		foreach($classes as $class) {
			if(singleton($class)->canCreate()) $createable[$class] = singleton($class)->singular_name();
		}

		if(count($classes) == 1) {
			return new LabelField('Class', current($createable));
		}

		return new DropdownField(
			'Class', '', $createable, '', null, ($includeSelf ? false : _t('AdvancedWorkflowAdmin.SELECT', '(select)'))
		);
	}

}

/**
 * A record controller that hides the "Back" button, and shows a message on deletion rather than redirecting to
 * the search form.
 *
 * @package    advancedworkflow
 * @subpackage admin
 */
class AdvancedWorkflowAdmin_RecordController extends ModelAdmin_RecordController {

	/**
	 * @return Form
	 */
	public function EditForm() {
		$form = parent::EditForm();
		$form->Actions()->removeByName('action_goBack');

		return $form;
	}

	/**
	 * @return string
	 */
	public function doDelete() {
		if($this->currentRecord->canDelete()) {
			$this->currentRecord->delete();

			$form = new Form(
				$this,
				'EditForm',
				new FieldSet(new LiteralField(
					'RecordDeleted',
					'<p>' . _t('AdvancedWorkflowAdmin.RECORDDELETED', 'This record has been deleted.') . '</p>'
				)),
				new FieldSet()
			);
			return $form->forTemplate();
		} else {
			return $this->redirectBack();
		}
	}

}