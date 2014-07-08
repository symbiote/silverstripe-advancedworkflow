<?php
/**
 * An overall definition of a workflow
 *
 * The workflow definition has a series of steps to it. Each step has a series of possible transitions
 * that it can take - the first one that meets certain criteria is followed, which could lead to
 * another step.
 *
 * A step is either manual or automatic; an example 'manual' step would be requiring a person to review
 * a document. An automatic step might be to email a group of people, or to publish documents.
 * Basically, a manual step requires the interaction of someone to pick which action to take, an automatic
 * step will automatically determine what to do once it has finished.
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowDefinition extends DataObject {
	public static $db = array(
		'Title'				=> 'Varchar(128)',
		'Description'		=> 'Text',
		'Template'			=> 'Varchar',
		'TemplateVersion'	=> 'Varchar',
		'RemindDays'		=> 'Int',
		'Sort'				=> 'Int'
	);

	public static $default_sort = 'Sort';

	public static $has_many = array(
		'Actions'   => 'WorkflowAction',
		'Instances' => 'WorkflowInstance'
	);

	/**
	 * By default, a workflow definition is bound to a particular set of users or groups.
	 *
	 * This is covered across to the workflow instance - it is up to subsequent
	 * workflow actions to change this if needbe.
	 *
	 * @var array
	 */
	public static $many_many = array(
		'Users' => 'Member',
		'Groups' => 'Group'
	);

	public static $icon = 'advancedworkflow/images/definition.png';

	public static $default_workflow_title_base = 'My Workflow';

	public static $workflow_defs = array();

	public static $dependencies = array(
		'workflowService'		=> '%$WorkflowService',
	);
	
	/**
	 * @var WorkflowService
	 */
	public $workflowService;

	/**
	 * Gets the action that first triggers off the workflow
	 *
	 * @return WorkflowAction
	 */
	public function getInitialAction() {
		if($actions = $this->Actions()) return $actions->First();
	}

	/**
	 * Ensure a sort value is set and we get a useable initial workflow title.
	 */
	public function onBeforeWrite() {
		if(!$this->Sort) {
			$this->Sort = DB::query('SELECT MAX("Sort") + 1 FROM "WorkflowDefinition"')->value();
		}
		if(!$this->ID) {
			$this->Title = $this->getDefaultWorkflowTitle();
		}
		parent::onBeforeWrite();
	}
	
	/**
	 * After we've been written, check whether we've got a template and to then
	 * create the relevant actions etc.
	 */
	public function onAfterWrite() {
		parent::onAfterWrite();

		// Request via ImportForm where TemplateVersion is already set, so unset it
		$posted = Controller::curr()->getRequest()->postVars();
		if(isset($posted['_CsvFile']) && $this->TemplateVersion) {
			$this->TemplateVersion = null;
		}
		if($this->numChildren() == 0 && $this->Template && !$this->TemplateVersion) {
			$this->workflowService->defineFromTemplate($this, $this->Template);
		}
	}
	
	/**
	 * Ensure all WorkflowDefinition relations are removed on delete. If we don't do this, 
	 * we see issues with targets previously under the control of a now-deleted workflow, 
	 * becoming stuck, even if a new workflow is subsequently assigned to it.
	 *
	 * @return null
	 */
	public function onBeforeDelete() {
		parent::onBeforeDelete();
		
		// Delete related import
		$this->deleteRelatedImport();
		
		// Reset/unlink related HasMany|ManyMany relations and their orphaned objects
		$this->removeRelatedHasLists();
	}

	/**
	 * Removes User+Group relations from this object as well as WorkflowAction relations.
	 * When a WorkflowAction is deleted, its own relations are also removed:
	 * - WorkflowInstance
	 * - WorkflowTransition
	 * @see WorkflowAction::onAfterDelete()
	 * 
	 * @return void
	 */
	private function removeRelatedHasLists() {
		$this->Users()->removeAll();
		$this->Groups()->removeAll();
		$this->Actions()->each(function($action) {
			if($orphan = DataObject::get_by_id('WorkflowAction', $action->ID)) {
				$orphan->delete();
			}
		});
	}

	/**
	 * 
	 * Deletes related ImportedWorkflowTemplate objects.
	 * 
	 * @return void
	 */
	private function deleteRelatedImport() {
		if($import = DataObject::get('ImportedWorkflowTemplate')->filter('DefinitionID', $this->ID)->first()) {
			$import->delete();
		}
	}

	/**
	 * @return int
	 */
	public function numChildren() {
		return count($this->Actions());
	}

	public function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);
		$labels['Title'] = _t('WorkflowDefinition.TITLE', 'Title');
		$labels['Description'] = _t('WorkflowDefinition.DESCRIPTION', 'Description');
		$labels['Template'] = _t('WorkflowDefinition.TEMPLATE_NAME', 'Source Template');
		$labels['TemplateVersion'] = _t('WorkflowDefinition.TEMPLATE_VERSION', 'Template Version');

		return $labels;
	}

	public function getCMSFields() {
		
		$cmsUsers = Member::mapInCMSGroups();
		
		$fields = new FieldList(new TabSet('Root'));

		$fields->addFieldToTab('Root.Main', new TextField('Title', $this->fieldLabel('Title')));
		$fields->addFieldToTab('Root.Main', new TextareaField('Description', $this->fieldLabel('Description')));
		if($this->ID) {
			$fields->addFieldToTab('Root.Main', new CheckboxSetField('Users', _t('WorkflowDefinition.USERS', 'Users'), $cmsUsers));
			$fields->addFieldToTab('Root.Main', new TreeMultiselectField('Groups', _t('WorkflowDefinition.GROUPS', 'Groups'), 'Group'));
		}

		if (class_exists('AbstractQueuedJob')) {
			$before = _t('WorkflowDefinition.SENDREMINDERDAYSBEFORE', 'Send reminder email after ');
			$after  = _t('WorkflowDefinition.SENDREMINDERDAYSAFTER', ' days without action.');

			$fields->addFieldToTab('Root.Main', new FieldGroup(
				_t('WorkflowDefinition.REMINDEREMAIL', 'Reminder Email'),
				new LabelField('ReminderEmailBefore', $before),
				new NumericField('RemindDays', ''),
				new LabelField('ReminderEmailAfter', $after)
			));
		}		

		if($this->ID) {
			if ($this->Template) {
				$template = $this->workflowService->getNamedTemplate($this->Template);
				$fields->addFieldToTab('Root.Main', new ReadonlyField('Template', $this->fieldLabel('Template'), $this->Template));
				$fields->addFieldToTab('Root.Main', new ReadonlyField('TemplateDesc', _t('WorkflowDefinition.TEMPLATE_INFO', 'Template Info'), $template ? $template->getDescription() : ''));
				$fields->addFieldToTab('Root.Main', $tv = new ReadonlyField('TemplateVersion', $this->fieldLabel('TemplateVersion')));
				$tv->setRightTitle(sprintf(_t('WorkflowDefinition.LATEST_VERSION', 'Latest version is %s'), $template ? $template->getVersion() : ''));
				
			}

			$fields->addFieldToTab('Root.Main', new WorkflowField(
				'Workflow', _t('WorkflowDefinition.WORKFLOW', 'Workflow'), $this 
			));
		} else {
			// add in the 'template' info
			$templates = $this->workflowService->getTemplates();
			
			if (is_array($templates)) {
				$items = array('' => '');
				foreach ($templates as $template) {
					$items[$template->getName()] = $template->getName();
				}
				$templates = array_combine(array_keys($templates), array_keys($templates));
				
				$fields->addFieldToTab('Root.Main', $dd = new DropdownField('Template', _t('WorkflowDefinition.CHOOSE_TEMPLATE', 'Choose template (optional)'), $items));
				$dd->setRightTitle(_t('WorkflowDefinition.CHOOSE_TEMPLATE_RIGHT', 'If set, this workflow definition will be automatically updated if the template is changed'));
			}
			
			/*
			 * Uncomment to allow pre-uploaded exports to appear in a new DropdownField.
			 * 
			 * $import = singleton('WorkflowDefinitionImporter')->getImportedWorkflows();
			 * if (is_array($import)) {
			 * $_imports = array('' => '');
			 * foreach ($imports as $import) {
			 * 		$_imports[$import->getName()] = $import->getName();
			 * }
			 * $imports = array_combine(array_keys($_imports), array_keys($_imports));
			 * $fields->addFieldToTab('Root.Main', new DropdownField('Import', _t('WorkflowDefinition.CHOOSE_IMPORT', 'Choose import (optional)'), $imports));
			 * }
			 */			
			
			$message = _t(
				'WorkflowDefinition.ADDAFTERSAVING',
				'You can add workflow steps after you save for the first time.'
			);
			$fields->addFieldToTab('Root.Main', new LiteralField(
				'AddAfterSaving', "<p class='message notice'>$message</p>"
			));
		}

		if($this->ID && Permission::check('VIEW_ACTIVE_WORKFLOWS')) {
			$active = $this->Instances()->filter(array(
				'WorkflowStatus' => array('Active', 'Paused')
			));

			$active = new GridField(
				'Active',
				_t('WorkflowDefinition.WORKFLOWACTIVEIINSTANCES', 'Active Workflow Instances'),
				$active,
				new GridFieldConfig_RecordEditor());

			$active->getConfig()->removeComponentsByType('GridFieldAddNewButton');
			$active->getConfig()->removeComponentsByType('GridFieldDeleteAction');

			if(!Permission::check('REASSIGN_ACTIVE_WORKFLOWS')) {
				$active->getConfig()->removeComponentsByType('GridFieldEditButton');
				$active->getConfig()->addComponent(new GridFieldViewButton());
				$active->getConfig()->addComponent(new GridFieldDetailForm());
			}

			$completed = $this->Instances()->filter(array(
				'WorkflowStatus' => array('Complete', 'Cancelled')
			));

			$config = new GridFieldConfig_Base();
			$config->addComponent(new GridFieldEditButton());
			$config->addComponent(new GridFieldDetailForm());
			
			$completed = new GridField(
				'Completed',
				_t('WorkflowDefinition.WORKFLOWCOMPLETEDIINSTANCES', 'Completed Workflow Instances'),
				$completed,
				$config);

			$fields->findOrMakeTab(
				'Root.Active',
				_t('WorkflowEmbargoExpiryExtension.ActiveWorkflowStateTitle', 'Active')
			);
			$fields->addFieldToTab('Root.Active', $active);

			$fields->findOrMakeTab(
				'Root.Completed',
				_t('WorkflowEmbargoExpiryExtension.CompletedWorkflowStateTitle', 'Completed')
			);
			$fields->addFieldToTab('Root.Completed', $completed);
		}
		
		$this->extend('updateCMSFields', $fields);

		return $fields;
	}
	
	public function updateAdminActions($actions) {
		if ($this->Template) {
			$template = $this->workflowService->getNamedTemplate($this->Template);
			if ($template && $this->TemplateVersion != $template->getVersion()) {
				$label = sprintf(_t('WorkflowDefinition.UPDATE_FROM_TEMLPATE', 'Update to latest template version (%s)'), $template->getVersion());
				$actions->push($action = FormAction::create('updatetemplateversion', $label));
			}
		}
	}
	
	public function updateFromTemplate() {
		if ($this->Template) {
			$template = $this->workflowService->getNamedTemplate($this->Template);
			$template->updateDefinition($this);
		}
	}

	/**
	 * If a workflow-title doesn't already exist, we automatically create a suitable default title
	 * when users attempt to create title-less workflow definitions or upload/create Workflows that would
	 * otherwise have the same name.
	 *
	 * @return string
	 * @todo	Filter query on current-user's workflows. Avoids confusion when other users may already have 'My Workflow 1' 
	 *			and user sees 'My Workflow 2'
	 */
	public function getDefaultWorkflowTitle() {
		// Where is the title coming from that we wish to test?
		$incomingTitle = $this->incomingTitle();
		$defs = DataObject::get('WorkflowDefinition')->map()->toArray();
		$tmp = array();
		
		foreach($defs as $def) {
			$parts = preg_split("#\s#", $def, -1, PREG_SPLIT_NO_EMPTY);		
			$lastPart = array_pop($parts);
			$match = implode(' ', $parts);
			// @todo do all this in one preg_match_all() call
			if(preg_match("#$match#", $incomingTitle)) {
				// @todo use a simple incrementer??
				if($incomingTitle.' '.$lastPart == $def) {
					array_push($tmp, $lastPart);
				}
			}
		}

		$incr = 1;
		if(count($tmp)) {
			sort($tmp,SORT_NUMERIC);
			$incr = (int)end($tmp)+1;
		}
		return $incomingTitle.' '.$incr;
	}
	
	/**
	 * Return the workflow definition title according to the source
	 * 
	 * @return string
	 */
	public function incomingTitle() {
		$req = Controller::curr()->getRequest();
		if(isset($req['_CsvFile']['name']) && !empty($req['_CsvFile']['name'])) {
			$import = DataObject::get('ImportedWorkflowTemplate')->filter('Filename', $req['_CsvFile']['name'])->first();
			$incomingTitle = $import->Name;
		}
		else if(isset($req['Template']) && !empty($req['Template'])) {
			$incomingTitle = $req['Template'];
		}
		else if(isset($req['Title']) && !empty($req['Title'])) {
			$incomingTitle = $req['Title'];
		}
		else {
			$incomingTitle = self::$default_workflow_title_base;
		}
		return $incomingTitle;
	}

	/**
	 * 
	 * @param Member $member
	 * @return boolean
	 */
	public function canCreate($member=null) {
		if (is_null($member)) {
			if (!Member::currentUserID()) {
				return false;
			}
			$member = Member::currentUser();
		}
		return Permission::checkMember($member, 'CREATE_WORKFLOW');
	}
	
	/**
	 * 
	 * @param Member $member
	 * @return boolean
	 */	
	public function canView($member=null) {
		return $this->userHasAccess($member);
	}
	
	/**
	 *
	 * @param Member $member
	 * @return boolean
	 */
	public function canEdit($member=null) {
		return $this->canCreate($member);
	}
	
	/**
	 *
	 * @param Member $member
	 * @return boolean
	 */
	public function canDelete($member=null) {
		if(!$this->canCreate($member)) {
			return false;
		}
		/*
		 * When a definition is deleted, remove all relations to prevent CMS issues,
		 * but we need to check we're permitted to do this first.
		 */
		$canDeleteAction = WorkflowAction::create()->canDelete();
		$canDeleteInstance = WorkflowInstance::create()->canDelete();
		return ($canDeleteAction && $canDeleteInstance);
	}	

	/**
	 * Checks whether the passed user is able to view this ModelAdmin
	 *
	 * @param $memberID
	 */
	protected function userHasAccess($member) {
		if (!$member) {
			if (!Member::currentUserID()) {
				return false;
			}
			$member = Member::currentUser();
		}

		if(Permission::checkMember($member, "VIEW_ACTIVE_WORKFLOWS")) {
			return true;
		}
	}	
}
