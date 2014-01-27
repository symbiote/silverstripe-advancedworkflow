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
	
	/**
	 *
	 * @var array Allowable actions on this controller.
	 */
	private static $allowed_actions = array(
		'export',
		'ImportForm'
	);
	
	private static $url_handlers = array(
		'$ModelClass/export/$ID!' => 'export',
		'$ModelClass/$Action' => 'handleAction',
		'' => 'index'
	);

	public static $managed_models  = 'WorkflowDefinition';
	
	public static $model_importers = array(
		'WorkflowDefinition' => 'WorkflowBulkLoader'
	);
	
	public static $dependencies = array(
		'workflowService'		=> '%$WorkflowService',
	);

	public static $fileEditActions = 'getCMSActions';

	/**
	 * Defaults are set in {@link getEditForm()}.
	 * 
	 * @var array
	 */
	public static $fieldOverrides = array();
	
	/**
	 * @var WorkflowService
	 */
	public $workflowService;
	
	/**
	 * Initialise javascript translation files
	 * 
	 * @return void
	 */
	public function init() {
		parent::init();
		Requirements::add_i18n_javascript('advancedworkflow/javascript/lang');
		Requirements::javascript('advancedworkflow/javascript/WorkflowField.js');
		Requirements::javascript('advancedworkflow/javascript/WorkflowGridField.js');
		Requirements::css('advancedworkflow/css/WorkflowField.css');		
	}	

	/*
	 * Shows up to x2 GridFields for Pending and Submitted items, dependent upon the current CMS user and that user's permissions
	 * on the objects showing in each field.
	 */
	public function getEditForm($id = null, $fields = null) {
		$form = parent::getEditForm($id, $fields);
		
		// Show items submitted into a workflow for current user to action
		$fieldName = 'PendingObjects';
		$pending = $this->userObjects(Member::currentUser(), $fieldName);

		if(self::$fieldOverrides) {
			$displayFields = self::$fieldOverrides;
		} else {
			$displayFields = array(
				'Title'				=> _t('AdvancedWorkflowAdmin.Title', 'Title'),
				'LastEdited'		=> _t('AdvancedWorkflowAdmin.LastEdited', 'Changed'),
				'WorkflowTitle'		=> _t('AdvancedWorkflowAdmin.WorkflowTitle', 'Effective workflow'),
				'WorkflowStatus'	=> _t('AdvancedWorkflowAdmin.WorkflowStatus', 'Current action'),
			);
		}

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
			$dataColumns->setDisplayFields($displayFields);

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
			$dataColumns->setDisplayFields($displayFields);

			$formFieldBottom->setForm($form);
			$formFieldBottom->getConfig()->removeComponentsByType('GridFieldEditButton');
			$formFieldBottom->getConfig()->addComponent(new GridFieldWorkflowRestrictedEditButton());
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
			$grid->getConfig()->addComponent(new GridFieldExportAction());
			$grid->getConfig()->removeComponentsByType('GridFieldExportButton');
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
	 * Discreet method used by both intro gridfields to format the target object's links and clickable text
	 *
	 * @param GridFieldConfig $config
	 * @return array $fieldFormatting
	 */
	public function setFieldFormatting(&$config) {
		$fieldFormatting = array();
		// Parse the column information
		foreach($this->columns() as $source => $info) {
			if(isset($info['link']) && $info['link']) {
				$fieldFormatting[$source] = '<a href=\"$ObjectRecordLink\">$value</a>';
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
			$instance->setField('ObjectRecordLink', Controller::join_links(Director::absoluteBaseURL(), $target->CMSEditLink()));

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
	
	/**
	 * Spits out an exported version of the selected WorkflowDefinition for download.
	 * 
	 * @param \SS_HTTPRequest $request
	 * @return \SS_HTTPResponse
	 */
	public function export(SS_HTTPRequest $request) {
		$url = explode('/', $request->getURL());
		$definitionID = end($url);
		if($definitionID && is_numeric($definitionID)) {
			$exporter = new WorkflowDefinitionExporter($definitionID);
			$exportFilename = WorkflowDefinitionExporter::$export_filename_prefix.'-'.$definitionID.'.yml';
			$exportBody = $exporter->export();
			$fileData = array(
				'name' => $exportFilename,
				'mime' => 'text/x-yaml',
				'body' => $exportBody,
				'size' => $exporter->getExportSize($exportBody)
			);
			return $exporter->sendFile($fileData);
		}
	}	
	
	/**
	 * Required so we can simply change the visible label of the "Import" button and lose some redundant form-fields.
	 * 
	 * @return Form
	 */
	public function ImportForm() {
		$form = parent::ImportForm();
		if(!$form) {
			return;
		}
		
		$form->unsetAllActions();
		$newActionList = new FieldList(array(
			new FormAction('import', _t('AdvancedWorkflowAdmin.IMPORT', 'Import workflow'))
		));
		$form->Fields()->fieldByName('_CsvFile')->getValidator()->setAllowedExtensions(array('yml', 'yaml'));
		$form->Fields()->removeByName('EmptyBeforeImport');
		$form->setActions($newActionList);
		
		return $form;
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