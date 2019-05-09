<?php

namespace Symbiote\AdvancedWorkflow\Extensions;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\CMSPreviewable;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowActionInstance;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowDefinition;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowInstance;
use Symbiote\AdvancedWorkflow\Services\WorkflowService;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * DataObjects that have the WorkflowApplicable extension can have a
 * workflow definition applied to them. At some point, the workflow definition is then
 * triggered.
 *
 * @author  marcus@symbiote.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowApplicable extends DataExtension
{
    private static $has_one = [
        'WorkflowDefinition' => WorkflowDefinition::class,
    ];

    private static $many_many = [
        'AdditionalWorkflowDefinitions' => WorkflowDefinition::class
    ];

    private static $dependencies = [
        'workflowService' => '%$' . WorkflowService::class,
    ];

    /**
     *
     * Used to flag to this extension if there's a WorkflowPublishTargetJob running.
     * @var boolean
     */
    public $isPublishJobRunning = false;

    /**
     *
     * @param boolean $truth
     */
    public function setIsPublishJobRunning($truth)
    {
        $this->isPublishJobRunning = $truth;
    }

    /**
     *
     * @return boolean
     */
    public function getIsPublishJobRunning()
    {
        return $this->isPublishJobRunning;
    }

    /**
     *
     * @see {@link $this->isPublishJobRunning}
     * @return boolean
     */
    public function isPublishJobRunning()
    {
        $propIsSet = (bool) $this->getIsPublishJobRunning();
        return class_exists(AbstractQueuedJob::class) && $propIsSet;
    }

    /**
     * @var WorkflowService
     */
    public $workflowService;

    /**
     *
     * A cache var for the current workflow instance
     *
     * @var WorkflowInstance
     */
    protected $currentInstance;

    public function updateSettingsFields(FieldList $fields)
    {
        $this->updateFields($fields);
    }

    public function updateCMSFields(FieldList $fields)
    {
        if (!$this->owner->hasMethod('getSettingsFields')) {
            $this->updateFields($fields);
        }

        // Instantiate a hidden form field to pass the triggered workflow definition through,
        // allowing a dynamic form action.

        $fields->push(HiddenField::create(
            'TriggeredWorkflowID'
        ));
    }

    public function updateFields(FieldList $fields)
    {
        if (!$this->owner->ID) {
            return $fields;
        }

        $tab = $fields->fieldByName('Root') ? $fields->findOrMakeTab('Root.Workflow') : $fields;

        if (Permission::check('APPLY_WORKFLOW')) {
            $definition = new DropdownField(
                'WorkflowDefinitionID',
                _t('WorkflowApplicable.DEFINITION', 'Applied Workflow')
            );
            $definitions = $this->getWorkflowService()->getDefinitions()->map()->toArray();
            $definition->setSource($definitions);
            $definition->setEmptyString(_t('WorkflowApplicable.INHERIT', 'Inherit from parent'));
            $tab->push($definition);

            // Allow an optional selection of additional workflow definitions.

            if ($this->owner->WorkflowDefinitionID) {
                $fields->removeByName('AdditionalWorkflowDefinitions');
                unset($definitions[$this->owner->WorkflowDefinitionID]);
                $tab->push($additional = ListboxField::create(
                    'AdditionalWorkflowDefinitions',
                    _t('WorkflowApplicable.ADDITIONAL_WORKFLOW_DEFINITIONS', 'Additional Workflows')
                ));
                $additional->setSource($definitions);
            }
        }

        // Display the effective workflow definition.

        if ($effective = $this->getWorkflowInstance()) {
            $title = $effective->Definition()->Title;
            $tab->push(ReadonlyField::create(
                'EffectiveWorkflow',
                _t('WorkflowApplicable.EFFECTIVE_WORKFLOW', 'Effective Workflow'),
                $title
            ));
        }

        if ($this->owner->ID) {
            $config = new GridFieldConfig_Base();
            $config->addComponent(new GridFieldEditButton());
            $config->addComponent(new GridFieldDetailForm());

            $insts = $this->owner->WorkflowInstances();
            $log = new GridField(
                'WorkflowLog',
                _t('WorkflowApplicable.WORKFLOWLOG', 'Workflow Log'),
                $insts,
                $config
            );

            $tab->push($log);
        }
    }

    public function updateCMSActions(FieldList $actions)
    {
        $active = $this->getWorkflowService()->getWorkflowFor($this->owner);
        $c = Controller::curr();
        if ($c && $c->hasExtension(AdvancedWorkflowExtension::class) && !$this->owner->isArchived()) {
            if ($active) {
                if ($this->canEditWorkflow()) {
                    $workflowOptions = new Tab(
                        'WorkflowOptions',
                        _t(
                            'SiteTree.WorkflowOptions',
                            'Workflow options',
                            'Expands a view for workflow specific buttons'
                        )
                    );

                    $menu = $actions->fieldByName('ActionMenus');
                    if (!$menu) {
                        // create the menu for adding to any arbitrary non-sitetree object
                        $menu = $this->createActionMenu();
                        $actions->push($menu);
                    }

                    if (!$actions->fieldByName('ActionMenus.WorkflowOptions')) {
                        $menu->push($workflowOptions);
                    }

                    $transitions = $active->CurrentAction()->getValidTransitions();

                    foreach ($transitions as $transition) {
                        if ($transition->canExecute($active)) {
                            $action = FormAction::create('updateworkflow-' . $transition->ID, $transition->Title)
                                ->setAttribute('data-transitionid', $transition->ID);
                            $workflowOptions->push($action);
                        }
                    }

                    // $action = FormAction::create('updateworkflow', $active->CurrentAction() ?
                    // $active->CurrentAction()->Title :
                    // _t('WorkflowApplicable.UPDATE_WORKFLOW', 'Update Workflow'))
                    //  ->setAttribute('data-icon', 'navigation');

                    // $actions->fieldByName('MajorActions') ?
                    // $actions->fieldByName('MajorActions')->push($action) :
                    // $actions->push($action);
                }
            } else {
                // Instantiate the workflow definition initial actions.
                $definitions = $this->getWorkflowService()->getDefinitionsFor($this->owner);
                if ($definitions) {
                    $menu = $actions->fieldByName('ActionMenus');
                    if (is_null($menu)) {
                        // Instantiate a new action menu for any data objects.

                        $menu = $this->createActionMenu();
                        $actions->push($menu);
                    }
                    $tab = Tab::create(
                        'AdditionalWorkflows'
                    );
                    $addedFirst = false;
                    foreach ($definitions as $definition) {
                        if ($definition->getInitialAction() && $this->owner->canEdit()) {
                            $action = FormAction::create(
                                "startworkflow-{$definition->ID}",
                                $definition->InitialActionButtonText ?
                                    $definition->InitialActionButtonText :
                                    $definition->getInitialAction()->Title
                            )
                                ->addExtraClass('start-workflow')
                                ->setAttribute('data-workflow', $definition->ID)
                                ->addExtraClass('btn-primary');

                            // The first element is the main workflow definition,
                            // and will be displayed as a major action.
                            if (!$addedFirst) {
                                $addedFirst = true;
                                $action->setAttribute('data-icon', 'navigation');
                                $majorActions = $actions->fieldByName('MajorActions');
                                $majorActions ? $majorActions->push($action) : $actions->push($action);
                            } else {
                                $tab->push($action);
                            }
                        }
                    }
                    // Only display menu if actions pushed to it
                    if ($tab->Fields()->exists()) {
                        $menu->insertBefore($tab, 'MoreOptions');
                    }
                }
            }
        }
    }

    protected function createActionMenu()
    {
        $rootTabSet = new TabSet('ActionMenus');
        $rootTabSet->addExtraClass('ss-ui-action-tabset action-menus');
        return $rootTabSet;
    }

    /**
     * Included in CMS-generated email templates for a NotifyUsersWorkflowAction.
     * Returns an absolute link to the CMS UI for a Page object
     *
     * @return string|null
     */
    public function AbsoluteEditLink()
    {
        $CMSEditLink = null;

        if ($this->owner instanceof CMSPreviewable) {
            $CMSEditLink = $this->owner->CMSEditLink();
        } elseif ($this->owner->hasMethod('WorkflowLink')) {
            $CMSEditLink = $this->owner->WorkflowLink();
        }

        if ($CMSEditLink === null) {
            return null;
        }

        return Controller::join_links(Director::absoluteBaseURL(), $CMSEditLink);
    }

    /**
     * Included in CMS-generated email templates for a NotifyUsersWorkflowAction.
     * Allows users to select a link in an email for direct access to the transition-selection dropdown in the CMS UI.
     *
     * @return string
     */
    public function LinkToPendingItems()
    {
        $urlBase = Director::absoluteBaseURL();
        $urlFrag = 'admin/workflows/Symbiote-AdvancedWorkflow-DataObjects-WorkflowDefinition/EditForm/field';
        $urlInst = $this->getWorkflowInstance();
        return Controller::join_links($urlBase, $urlFrag, 'PendingObjects', 'item', $urlInst->ID, 'edit');
    }

    /**
     * After a workflow item is written, we notify the
     * workflow so that it can take action if needbe
     */
    public function onAfterWrite()
    {
        $instance = $this->getWorkflowInstance();
        if ($instance && $instance->CurrentActionID) {
            $action = $instance->CurrentAction()->BaseAction()->targetUpdated($instance);
        }
    }

    public function WorkflowInstances()
    {
        return WorkflowInstance::get()->filter([
            'TargetClass' => $this->owner->baseClass(),
            'TargetID' => $this->owner->ID
        ]);
    }

    /**
     * Gets the current instance of workflow
     *
     * @return WorkflowInstance
     */
    public function getWorkflowInstance()
    {
        if (!$this->currentInstance) {
            $this->currentInstance = $this->getWorkflowService()->getWorkflowFor($this->owner);
        }

        return $this->currentInstance;
    }


    /**
     * Gets the history of a workflow instance
     *
     * @return DataList
     */
    public function getWorkflowHistory($limit = null)
    {
        return $this->getWorkflowService()->getWorkflowHistoryFor($this->owner, $limit);
    }

    /**
     * Check all recent WorkflowActionIntances and return the most recent one with a Comment
     *
     * @param int $limit
     * @return WorkflowActionInstance|null
     */
    public function RecentWorkflowComment($limit = 10)
    {
        if ($actions = $this->getWorkflowHistory($limit)) {
            foreach ($actions as $action) {
                if ($action->Comment != '') {
                    return $action;
                }
            }
        }
    }

    /**
     * Content can never be directly publishable if there's a workflow applied.
     *
     * If there's an active instance, then it 'might' be publishable
     */
    public function canPublish()
    {
        // Override any default behaviour, to allow queuedjobs to complete
        if ($this->isPublishJobRunning()) {
            return true;
        }

        if ($active = $this->getWorkflowInstance()) {
            $publish = $active->canPublishTarget($this->owner);
            if (!is_null($publish)) {
                return $publish;
            }
        }

        // use definition to determine if publishing directly is allowed
        $definition = $this->getWorkflowService()->getDefinitionFor($this->owner);

        if ($definition) {
            if (!Security::getCurrentUser()) {
                return false;
            }
            $member = Security::getCurrentUser();

            $canPublish = $definition->canWorkflowPublish($member, $this->owner);

            return $canPublish;
        }
    }

    /**
     * Can only edit content that's NOT in another person's content changeset
     *
     * @return bool
     */
    public function canEdit($member)
    {
        // Override any default behaviour, to allow queuedjobs to complete
        if ($this->isPublishJobRunning()) {
            return true;
        }

        if ($active = $this->getWorkflowInstance()) {
            return $active->canEditTarget();
        }
    }

    /**
     * Can a user edit the current workflow attached to this item?
     *
     * @return bool
     */
    public function canEditWorkflow()
    {
        $active = $this->getWorkflowInstance();
        if ($active) {
            return $active->canEdit();
        }
        return false;
    }

    /**
     * @param WorkflowService $workflowService
     * @return $this
     */
    public function setWorkflowService(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
        return $this;
    }

    /**
     * @return WorkflowService
     */
    public function getWorkflowService()
    {
        return $this->workflowService;
    }
}
