<?php

namespace Symbiote\AdvancedWorkflow\FormFields;

use ReflectionClass;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\SecurityToken;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowDefinition;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowAction;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowTransition;
use Symbiote\AdvancedWorkflow\FormFields\WorkflowField;
use Symbiote\AdvancedWorkflow\Services\WorkflowService;

/**
 * A form field that allows workflow actions and transitions to be edited,
 * while showing a visual overview of the flow.
 *
 * @package advancedworkflow
 */
class WorkflowField extends FormField
{
    private static $allowed_actions = array(
        'action',
        'transition',
        'sort'
    );

    protected $definition;

    public function __construct($name, $title, WorkflowDefinition $definition)
    {
        $this->definition = $definition;
        $this->addExtraClass('workflow-field');

        parent::__construct($name, $title);
    }

    public function action()
    {
        return new WorkflowFieldActionController($this, 'action');
    }

    public function transition()
    {
        return new WorkflowFieldTransitionController($this, 'transition');
    }

    public function sort($request)
    {
        if (!SecurityToken::inst()->checkRequest($request)) {
            $this->httpError(404);
        }

        $class = $request->postVar('class');
        $ids   = $request->postVar('id');

        if ($class == WorkflowAction::class) {
            $objects = $this->Definition()->Actions();
        } elseif ($class == WorkflowTransition::class) {
            $parent = $request->postVar('parent');
            $action = $this->Definition()->Actions()->byID($parent);

            if (!$action) {
                $this->httpError(400, _t(
                    'AdvancedWorkflowAdmin.INVALIDPARENTID',
                    'An invalid parent ID was specified.'
                ));
            }

            $objects = $action->Transitions();
        } else {
            $this->httpError(400, _t(
                'AdvancedWorkflowAdmin.INVALIDCLASSTOORDER',
                'An invalid class to order was specified.'
            ));
        }

        if (!$ids || array_diff($ids, $objects->column('ID'))) {
            $this->httpError(400, _t('AdvancedWorkflowAdmin.INVALIDIDLIST', 'An invalid list of IDs was provided.'));
        }

        singleton(WorkflowService::class)->reorder($objects, $ids);

        return new HTTPResponse(
            null,
            200,
            _t('AdvancedWorkflowAdmin.SORTORDERSAVED', 'The sort order has been saved.')
        );
    }

    public function getTemplate()
    {
        return __CLASS__;
    }

    public function FieldHolder($properties = array())
    {
        Requirements::javascript('symbiote/silverstripe-advancedworkflow:client/dist/js/advancedworkflow.js');
        Requirements::css('symbiote/silverstripe-advancedworkflow:client/dist/styles/advancedworkflow.css');

        return $this->Field($properties);
    }

    public function Definition()
    {
        return $this->definition;
    }

    public function ActionLink()
    {
        $parts = func_get_args();
        array_unshift($parts, 'action');

        return $this->Link(implode('/', $parts));
    }

    public function TransitionLink()
    {
        $parts = func_get_args();
        array_unshift($parts, 'transition');

        return $this->Link(implode('/', $parts));
    }

    public function CreateableActions()
    {
        $list    = ArrayList::create();
        $classes = ClassInfo::subclassesFor(WorkflowAction::class);

        array_shift($classes);
        sort($classes);

        foreach ($classes as $class) {
            $reflect = new ReflectionClass($class);
            $can     = singleton($class)->canCreate() && !$reflect->isAbstract();

            if ($can) {
                $list->push(ArrayData::create([
                    'Title' => singleton($class)->singular_name(),
                    'Class' => $this->sanitiseClassName($class),
                ]));
            }
        }

        return $list;
    }

    /**
     * Sanitise a model class' name for inclusion in a link
     *
     * @param string $class
     * @return string
     */
    protected function sanitiseClassName($class)
    {
        return str_replace('\\', '-', $class);
    }
}
