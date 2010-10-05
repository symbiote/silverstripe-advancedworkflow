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
 * @package activityworkflow
 */
class WorkflowDefinition extends DataObject {

	public static $db = array(
		'Title'       => 'Varchar(128)',
		'Description' => 'Text',
		'Sort'        => 'Int'
	);

	public static $default_sort = 'Sort';

	public static $has_many = array(
		'Actions' => 'WorkflowAction',
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

	public static $icon = 'activityworkflow/images/definition.png';

	/**
	 * Gets the action that first triggers off the workflow
	 * 
	 * @return WorkflowAction
	 */
	public function getInitialAction() {
		if($actions = $this->Actions()) return $actions->First();
	}

	public function onBeforeWrite() {
		if(!$this->Sort) {
			$this->Sort = DB::query('SELECT MAX("SORT") + 1 FROM "WorkflowDefinition"')->value();
		}

		parent::onBeforeWrite();
	}

	public function numChildren() {
		return count($this->Actions());
	}

	/**
	 */
	public function getCMSFields() {
		$fields = new FieldSet(new TabSet('Root'));

		$fields->addFieldToTab('Root.Main', new TextField('Title', _t('WorkflowDefinition.TITLE', 'Title')));
		$fields->addFieldToTab('Root.Main', new TextareaField('Description', _t('WorkflowDefinition.DESCRIPTION', 'Description')));
		$fields->addFieldToTab('Root.Main', new TreeMultiselectField('Users', _t('WorkflowDefinition.USERS', 'Users'), 'Member'));
		$fields->addFieldToTab('Root.Main', new TreeMultiselectField('Groups', _t('WorkflowDefinition.GROUPS', 'Groups'), 'Group'));

		return $fields;
	}
}