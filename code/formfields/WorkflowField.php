<?php
/**
 * A form field that allows workflow actions and transitions to be edited,
 * while showing a visual overview of the flow.
 *
 * @package advancedworkflow
 */
class WorkflowField extends FormField {

	public static $allowed_actions = array(
		'action',
		'transition',
		'sort'
	);

	protected $definition;

	public function __construct($name, $title, WorkflowDefinition $definition) {
		$this->definition = $definition;
		$this->addExtraClass('workflow-field');

		parent::__construct($name, $title);
	}

	public function action() {
		return new WorkflowFieldActionController($this, 'action');
	}

	public function transition() {
		return new WorkflowFieldTransitionController($this, 'transition');
	}

	public function sort($request) {
		if(!SecurityToken::inst()->checkRequest($request)) {
			$this->httpError(404);
		}

		$class = $request->postVar('class');
		$ids   = $request->postVar('id');

		if($class == 'WorkflowAction') {
			$objects = $this->Definition()->Actions();
		} elseif($class == 'WorkflowTransition') {
			$parent = $request->postVar('parent');
			$action = $this->Definition()->Actions()->byID($parent);

			if(!$action) {
				$this->httpError(400, 'An invalid parent ID was specified.');
			}

			$objects = $action->Transitions();
		} else {
			$this->httpError(400, 'An invalid class to order was specified.');
		}

		if(array_diff($ids, $objects->column('ID'))) {
			$this->httpError(400, 'An invalid list of IDs was provided.');
		}

		singleton('WorkflowService')->reorder($objects, $ids);

		return new SS_HTTPResponse(
			null, 200, _t('AdvancedWorkflowAdmin.SORTORDERSAVED', 'The sort order has been saved.')
		);
	}

	public function getTemplate() {
		return 'WorkflowField';
	}

	public function FieldHolder($properties = array()) {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-entwine/dist/jquery.entwine-dist.js');
		Requirements::javascript(ADVANCED_WORKFLOW_DIR . '/javascript/WorkflowField.js');
		Requirements::css(ADVANCED_WORKFLOW_DIR . '/css/WorkflowField.css');

		return $this->Field($properties);
	}

	public function Definition() {
		return $this->definition;
	}

	public function CreateableActions() {
		$list    = new ArrayList();
		$classes = ClassInfo::subclassesFor('WorkflowAction');

		array_shift($classes);
		sort($classes);

		foreach($classes as $class) {
			$reflect = new ReflectionClass($class);
			$can     = singleton($class)->canCreate() && !$reflect->isAbstract();

			if($can) $list->push(new ArrayData(array(
				'Title' => singleton($class)->singular_name(),
				'Class' => $class
			)));
		}

		return $list;
	}

}
