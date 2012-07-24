<?php
/**
 * Handles requests for creating or editing transitions.
 *
 * @package silverstripe-advancedworkflow
 */
class WorkflowFieldTransitionController extends RequestHandler {

	public static $url_handlers = array(
		'new/$ParentID!' => 'handleAdd',
		'item/$ID!'      => 'handleItem'
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
		$parent = $this->request->param('ParentID');
		$action = WorkflowAction::get()->byID($this->request->param('ParentID'));

		if(!$action || $action->WorkflowDefID != $this->RootField()->Definition()->ID) {
			$this->httpError(404);
		}

		if(!singleton('WorkflowTransition')->canCreate()) {
			$this->httpError(403);
		}

		$transition = new WorkflowTransition();
		$transition->ActionID = $action->ID;

		return new WorkflowFieldItemController($this, "new/$parent", $transition);
	}

	public function handleItem() {
		$id    = $this->request->param('ID');
		$trans = WorkflowTransition::get()->byID($id);

		if(!$trans || $trans->Action()->WorkflowDefID != $this->RootField()->Definition()->ID) {
			$this->httpError(404);
		}

		if(!$trans->canEdit()) {
			$this->httpError(403);
		}

		return new WorkflowFieldItemController($this, "item/$id", $trans);
	}

	public function RootField() {
		return $this->parent;
	}

	public function Link($action = null) {
		return Controller::join_links($this->parent->Link(), $this->name, $action);
	}

}