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
		'update',
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
		Requirements::javascript('activityworkflow/javascript/WorkflowAdmin.jquery.js');
		Requirements::javascript('activityworkflow/javascript/jstree-0.9.9.a2/jquery.tree.js');
	}

	public function EditForm($request=null, $vars=null) {
		$forUser = Member::currentUser();
		$id = (int) $this->request->param('ID');
		$workflow = null;
		if ($id) {
			$workflow = DataObject::get_by_id('WorkflowDefinition', $id);
		} else {
		}

		$form = null;

		if ($workflow) {
			$idField = new HiddenField('ID', '', $workflow->ID);

			$fields = $workflow->getCMSFields();

			$actions = new FieldSet();

			$actions->push(new FormAction('update', _t('ActivityWorkflow.UPDATE', 'Update')));
			$actions->push(new FormAction('delete', _t('ActivityWorkflow.DELETE', 'Delete')));

			$form = new Form($this, "EditForm", $fields, $actions);

			$form->loadDataFrom($workflow);
		}

		return $form;
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
	 * Return the entire site tree as a nested UL.
	 * @return string HTML for site tree
	 */
	public function SiteTreeAsUL() {
		$obj = singleton('WorkflowDefinition');
		$number = $obj->markPartialTree(1, null);

		if($p = $this->currentPage()) $obj->markToExpose($p);

		$titleEval = '"<li id=\"record-$child->ID\" class=\"$child->class" . $child->markingClasses() .  ($extraArg->isCurrentPage($child) ? " current" : "") . "\">" . ' .
			'"<a href=\"" . Controller::join_links(substr($extraArg->Link(),0,-1), "show", $child->ID) . "\" class=\" contents\" >" . $child->Title . "</a>" ';

		$this->generateTreeStylingJS();

		$siteTreeList = $obj->getChildrenAsUL(
			'',
			$titleEval,
			$this,
			true,
			'AllChildrenIncludingDeleted',
			'numChildren',
			true,
			1
		);

		// Wrap the root if needs be
		$rootLink = $this->Link() . 'show/root';
		$baseUrl = Director::absoluteBaseURL() . self::$url_segment;
		if(!isset($rootID)) {
			$siteTree = "<ul id=\"sitetree\" class=\"tree unformatted\"><li id=\"record-root\" class=\"Root\"><a href=\"$rootLink\"><strong>All Connectors</strong></a>"
			. $siteTreeList . "</li></ul>";
		}

		return $siteTree;
	}

	/**
	 * Returns a subtree of items underneath the given folder.
	 *
	 * We do our own version of returning tree data here - SilverStripe's base functionality is just too greedy
	 * with data for this to be happy.
	 */
	public function getsubtree() {
		$obj = ExternalContent::getDataObjectFor($_REQUEST['ID']);  //  DataObject::get_by_id('ExternalContentSource', $_REQUEST['ID']);

		$siteTreeList = '';
		if ($obj) {
			try {
				$children = $obj->stageChildren();
				if ($children) {
					foreach ($children as $child) {
						$siteTreeList .= '<li id="record-'.$child->ID.'" class="'.$child->class .' unexpanded closed">' .
						'<a href="' . Controller::join_links(substr($this->Link(),0,-1), "show", $child->ID) . '" class=" contents">' . $child->Title . '</a>';
					}
				}
			} catch (Exception $e) {
				singleton('ECUtils')->log("Failed creating tree: ".$e->getMessage(), SS_Log::ERR);
				singleton('ECUtils')->log($e->getTraceAsString(), SS_Log::ERR);
			}
		}

		return $siteTreeList;
	}


	/**
	 * Get the form used to create a new workflow
	 *
	 * @return Form
	 */
	public function CreateWorkflowForm() {
		$classes = ClassInfo::subclassesFor(self::$tree_class);

		$fields = new FieldSet(
			new HiddenField("ParentID"),
			new HiddenField("Locale", 'Locale', Translatable::get_current_locale()),
			new DropdownField("WorkflowDefinitionType", "", $classes)
		);

		$actions = new FieldSet(
			new FormAction("createworkflow", _t('WorkflowAdmin.CREATE',"Create"))
		);

		return new Form($this, "CreateWorkflowForm", $fields, $actions);
	}

	/**
	 * Create a new workflow
	 */
	public function createworkflow($data, $form, $request) {
		$workflow = new WorkflowDefinition();
		$workflow->Title = _t('WorkflowAdmin.NEW_WORKFLOW', 'New Workflow');
		$workflow->write();

		if(isset($_REQUEST['returnID'])) {
			return $workflow->ID;
		} else {
			return $this->returnItemToUser($workflow);
		}
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