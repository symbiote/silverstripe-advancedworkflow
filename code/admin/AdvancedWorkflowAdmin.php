<?php
/**
 * @package advancedworkflow
 * @todo UI/UX needs looking at for when current user has no pending and/or submitted items, (Current implementation is bog-standard <p> text)
 */
class AdvancedWorkflowAdmin extends ModelAdmin {

	public static $menu_title    = 'Workflows';
	public static $menu_priority = -1;
	public static $url_segment   = 'workflows';
	private static $menu_icon = "advancedworkflow/images/workflow-menu-icon.png";


	public static $managed_models  = 'WorkflowDefinition';
	public static $model_importers = array();
	
	public static $dependencies = array(
		'workflowService'		=> '%$WorkflowService',
	);

	public static $fileEditActions = 'getCMSActions';

	public static $fieldOverrides = array(
		'Title'				=> 'Title',
		'LastEdited'		=> 'Changed',
		'WorkflowTitle'		=> 'Effective workflow',
		'WorkflowStatus'	=> 'Current action'
	);
	
	/**
	 * @var WorkflowService
	 */
	public $workflowService;

	/*
	 * Shows up to x2 GridFields for Pending and Submitted items, dependent upon the current CMS user and that user's permissions
	 * on the objects showing in each field.
	 */
	public function getEditForm($id = null, $fields = null) {
		$form = parent::getEditForm($id, $fields);
		
		// Show items submitted into a workflow for current user to action
		$fieldName = 'PendingObjects';
		$pending = $this->userObjects(Member::currentUser(), $fieldName);

		// Pending/Submitted items GridField Config
		$config = new GridFieldConfig_Base();
		$config->addComponent(new GridFieldEditButton());
		$config->addComponent(new GridFieldDetailForm());
		$config->getComponentByType('GridFieldPaginator')->setItemsPerPage(5);
		$columns = $config->getComponentByType('GridFieldDataColumns');
		$columns->setFieldFormatting($this->setFieldFormatting($config));

		if($pending->count()) {
			$formFieldTop = GridField::create(
				$fieldName,
				$this->isAdminUser(Member::currentUser())?
					_t(
						'AdvancedWorkflowAdmin.GridFieldTitleAssignedAll',
						'All pending items'
						):
					_t(
						'AdvancedWorkflowAdmin.GridFieldTitleAssignedYour',
						'Your pending items'),
				$pending,
				$config
			);

			$dataColumns = $formFieldTop->getConfig()->getComponentByType('GridFieldDataColumns');
			$dataColumns->setDisplayFields(self::$fieldOverrides);

			$formFieldTop->setForm($form);
			$form->Fields()->insertBefore($formFieldTop, 'WorkflowDefinition');
		}

		// Show items submitted into a workflow by current user
		$fieldName = 'SubmittedObjects';
		$submitted = $this->userObjects(Member::currentUser(), $fieldName);
		if($submitted->count()) {
			$formFieldBottom = GridField::create(
				$fieldName,
				$this->isAdminUser(Member::currentUser())?
					_t(
						'AdvancedWorkflowAdmin.GridFieldTitleSubmittedAll',
						'All submitted items'
						):
					_t(
						'AdvancedWorkflowAdmin.GridFieldTitleSubmittedYour',
						'Your submitted items'),
				$submitted,
				$config
			);

			$dataColumns = $formFieldBottom->getConfig()->getComponentByType('GridFieldDataColumns');
			$dataColumns->setDisplayFields(self::$fieldOverrides);

			$formFieldBottom->setForm($form);
			$form->Fields()->insertBefore($formFieldBottom, 'WorkflowDefinition');
		}
		
		$grid = $form->Fields()->dataFieldByName('WorkflowDefinition');
		if ($grid) {
			$grid->getConfig()->getComponentByType('GridFieldDetailForm')->setItemEditFormCallback(function ($form) {
				$record = $form->getRecord();
				if ($record) {
					$record->updateAdminActions($form->Actions());
				}
			});
			
			$grid->getConfig()->getComponentByType('GridFieldDetailForm')->setItemRequestClass('WorkflowDefinitionItemRequestClass');
		}
		
		return $form;
	}

	/*
	 * @param Member $user
	 * @return boolean
	 */
	public function isAdminUser(Member $user) {
		if(Permission::checkMember($user, 'ADMIN')) {
			return true;
		}
		return false;
	}

	/*
	 * By default, we implement GridField_ColumnProvider to allow users to click through to the PagesAdmin.
	 * We would also like a "Quick View", that allows users to quickly make a decision on a given workflow-bound content-object
	 */
	public function columns() {
		$fields = array(
			'Title' => array(
				'link' => function($value, $item) {
					$pageAdminLink = singleton('CMSPageEditController')->Link('show');
					return sprintf('<a href="%s/%s">%s</a>',$pageAdminLink,$item->Link,$value);
				}
			),
			'WorkflowStatus' => array(
				'text' => function($value, $item) {
					return $item->WorkflowCurrentAction;
				}
			)
		);
		return $fields;
	}

	/*
	 * Discreet method used by both intro gridfields
	 *
	 * @param GridFieldConfig $config
	 * @return array $fieldFormatting
	 */
	public function setFieldFormatting(&$config) {
		$fieldFormatting = array();
		// Parse the column information
		foreach($this->columns() as $source => $info) {
			if(isset($info['link']) && $info['link']) {
				$link = singleton('CMSPageEditController')->Link('show');
				$fieldFormatting[$source] = '<a href=\"' . $link . '/$ObjectRecordID\">$value</a>';
			}
			if(isset($info['text']) && $info['text']) {
				$fieldFormatting[$source] = $info['text'];
			}
		}
		return $fieldFormatting;
	}

	/**
	 * Get WorkflowInstance Target objects to show for users in initial gridfield(s)
	 *
	 * @param Member $member
	 * @param string $fieldName The name of the gridfield that determines which dataset to return
	 * @return DataList
	 * @todo Add the ability to see embargo/expiry dates in report-gridfields at-a-glance if QueuedJobs module installed
	 */
	public function userObjects(Member $user, $fieldName) {
		$list = new ArrayList();
		$userWorkflowInstances = $this->getFieldDependentData($user, $fieldName);
		foreach($userWorkflowInstances as $instance) {
			if(!$instance->TargetID || !$instance->DefinitionID) {
				continue;
			}
			// @todo can we use $this->getDefinitionFor() to fetch the "Parent" definition of $instance? Maybe define $this->workflowParent()
			$effectiveWorkflow = DataObject::get_by_id('WorkflowDefinition', $instance->DefinitionID);
			$target = $instance->getTarget();
			if(!is_object($effectiveWorkflow) || !$target) {
				continue;
			}
			$instance->setField('WorkflowTitle',$effectiveWorkflow->getField('Title'));
			$instance->setField('WorkflowCurrentAction',$instance->getCurrentAction());
			// Note the order of property-setting here, somehow $instance->Title is overwritten by the Target Title property..
			$instance->setField('Title',$target->getField('Title'));
			$instance->setField('LastEdited',$target->getField('LastEdited'));
			$instance->setField('ObjectRecordID',$target->getField('ID')); // Forces a different default ID for linking to the pagesAdmin from a GridField
			$list->push($instance);
		}
		return $list;
	}

	/*
	 * Return content-object data depending on which gridfeld is calling for it
	 *
	 * @param Member $user
	 * @param string $fieldName
	 */
	public function getFieldDependentData(Member $user, $fieldName) {
		if($fieldName == 'PendingObjects') {
			return $this->workflowService->userPendingItems($user);
		}
		if($fieldName == 'SubmittedObjects') {
			return $this->workflowService->userSubmittedItems($user);
		}
	}
}

class WorkflowDefinitionItemRequestClass extends GridFieldDetailForm_ItemRequest {
	public function updatetemplateversion($data, Form $form, $request) {
		$record = $form->getRecord();
		if ($record) {
			$record->updateFromTemplate();
		}
		return $form->loadDataFrom($form->getRecord())->forAjaxTemplate();
	}
}