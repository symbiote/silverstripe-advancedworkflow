<?php
/**
 * @package advancedworkflow
 * @todo UI/UX needs looking at for when current user has no pending and/or submitted items, (Current implementation is bog-standard <p> text)
 */
class AdvancedWorkflowAdmin extends ModelAdmin {

	public static $menu_title    = 'Workflows';
	public static $menu_priority = -1;
	public static $url_segment   = 'workflows';

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
		$pending = $this->workflowService->userObjects(Member::currentUser(), $fieldName);

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
		$submitted = $this->workflowService->userObjects(Member::currentUser(), $fieldName);
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
}