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
		'transition'
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
