<?php
/**
 * An admin interface for managing workflow definitions, actions and transitions.
 *
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    activityworkflow
 * @subpackage admin
 */
class ActivityWorkflowAdmin extends ModelAdmin {

	public static $title = 'Workflows';
	public static $menu_title = 'Workflows';
	public static $url_segment = 'workflows';

	public static $managed_models = array(
		'WorkflowDefinition' => array('record_controller' => 'ActivityWorkflowAdmin_RecordController'),
		'WorkflowAction'     => array('record_controller' => 'ActivityWorkflowAdmin_RecordController'),
		'WorkflowTransition' => array('record_controller' => 'ActivityWorkflowAdmin_RecordController')
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
				return $this->httpError(400, _t('ActivityWorkflow.INVALIDSORTTYPE', 'Invalid sort type.'));
		}

		if(!$order || count($order) != count($current)) {
			return new SS_HTTPResponse(
				null, 400, _t('ActivityWorkflow.INVALIDSORT', 'An invalid sort order was specified.')
			);
		}

		$service->reorder($current, $order);

		return new SS_HTTPResponse(
			null, 200, _t('ActivityWorkflow.SORTORDERSAVED', 'The sort order has been saved.')
		);
	}

	/**
	 * @return Form
	 */
	public function CreateDefinitionForm() {
		$form = $this->WorkflowDefinition()->CreateForm();
		$form->setActions(new FieldSet(
			new FormAction('add', _t('ActivityWorkflowAdmin.CREATEWORKFLOW', 'Create Workflow'))
		));

		return $form;
	}

	/**
	 * @return Form
	 */
	public function CreateActionForm() {
		return new Form(
			$this,
			'CreateActionForm',
			new FieldSet(
				new DropdownField('ActionClass', '', WorkflowAction::get_dropdown_map(), '', null,
					_t('ActivityWorkflowAdmin.SELECT', '(select)')),
				new HiddenField('ParentID')
			),
			new FieldSet(new FormAction(
				'doCreateAction', _t('ActivityWorkflowAdmin.CREATEACTION', 'Create Action')
			))
		);
	}

	/**
	 * @param array $data
	 * @param Form $form
	 */
	public function doCreateAction($data, $form) {
		$definitionId = $data['ParentID'];
		$actionClass  = $data['ActionClass'];

		if(!DataObject::get_by_id('WorkflowDefinition', $definitionId)) {
			return new SS_HTTPResponse(
				null, 400, _t('ActivityWorkflowAdmin.INVALIDDEFID', 'An invalid definition ID was specified.')
			);
		}

		if(!class_exists($actionClass) || !is_subclass_of($actionClass, 'WorkflowAction')) {
			return new SS_HTTPResponse(
				null, 400, _t('ActivityWorkflowAdmin.INVALIDACTIONCLASS', 'An invalid action class was specified')
			);
		}

		$action = new $actionClass();
		$action->Title = 'New ' . $action->singular_name();
		$action->WorkflowDefID = $definitionId;
		$action->write();

		$controller = $this->getRecordControllerClass('WorkflowAction');
		$controller = new $controller($this->WorkflowAction(), null, $action->ID);
		$form       = $controller->EditForm();

		return $this->isAjax() ? $form->forAjaxTemplate() : $form->forTemplate();
	}

	/**
	 * @return Form
	 */
	public function CreateTransitionForm() {
		return new Form(
			$this,
			'CreateTransitionForm',
			new FieldSet(new HiddenField('ParentID')),
			new FieldSet(new FormAction(
				'doCreateTransition', _t('ActivityWorkflowAdmin.CREATETRANSITION', 'Create Transition')
			))
		);
	}

	/**
	 * @param array $data
	 * @param Form $form
	 */
	public function doCreateTransition($data, $form) {
		$actionId = $data['ParentID'];

		if(!DataObject::get_by_id('WorkflowAction', $actionId)) {
			return new SS_HTTPResponse(
				null, 400, _t('ActivityWorkflowAdmin.INVALIDACTIONID', 'An invalid action ID was specified.')
			);
		}

		$form = $this->WorkflowTransition()->AddForm();
		$form->dataFieldByName('ActionID')->setValue($actionId);

		return new SS_HTTPResponse(
			$form->forAjaxTemplate(),
			200,
			_t('ActivityWorkflowAdmin.FILLTOADDTRANSITION', 'Fill out this form to add a transition to the database.')
		);
	}

}

/**
 * A record controller that hides the "Back" button, and shows a message on deletion rather than redirecting to
 * the search form.
 *
 * @package    activityworkflow
 * @subpackage admin
 */
class ActivityWorkflowAdmin_RecordController extends ModelAdmin_RecordController {

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
					'<p>' . _t('ActivityWorkflowAdmin.RECORDDELETED', 'This record has been deleted.') . '</p>'
				)),
				new FieldSet()
			);
			return $form->forTemplate();
		} else {
			return $this->redirectBack();
		}
	}

}