<?php
/**
 * Handles requests for creating or editing actions.
 *
 * @package silverstripe-advancedworkflow
 */
class WorkflowFieldActionController extends RequestHandler {

	public static $url_handlers = array(
		'new/$Class' => 'handleAdd',
		'item/$ID'   => 'handleItem'
	);

	public static $allowed_actions = array(
		'handleAdd',
		'handleItem'
	);

	protected $parent;
	protected $name;

	public function __construct($parent, $name) {
		$this->parent = $parent;
		$this->name   = $name;

		parent::__construct();
	}

	public function handleAdd() {
		$class = $this->request->param('Class');

		if(!class_exists($class) || !is_subclass_of($class, 'WorkflowAction')) {
			$this->httpError(400);
		}

		$reflector = new ReflectionClass($class);

		if($reflector->isAbstract() || !singleton($class)->canCreate()) {
			$this->httpError(400);
		}

		$record = new $class();
		$record->WorkflowDefID = $this->parent->Definition()->ID;

		return new WorkflowFieldItemController($this, "new/$class", $record);
	}

	public function handleItem() {
		$id     = $this->request->param('ID');
		$defn   = $this->parent->Definition();
		$action = $defn->Actions()->byID($id);

		if(!$action) {
			$this->httpError(404);
		}

		if(!$action->canEdit()) {
			$this->httpError(403);
		}

		return new WorkflowFieldItemController($this, "item/$id", $action);
	}

	public function RootField() {
		return $this->parent;
	}

	public function Link($action = null) {
		return Controller::join_links($this->parent->Link(), $this->name, $action);
	}

}