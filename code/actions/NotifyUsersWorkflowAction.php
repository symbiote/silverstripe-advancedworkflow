<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * Workflow action that notifies users on the workflow that they
 * have a task waiting for them
 *
 * @author marcus@silverstripe.com.au
 */
class NotifyUsersWorkflowAction extends WorkflowAction {

	public static $icon = 'activityworkflow/images/notify.png';
	
    public static $db = array(
		'Subject' => 'Varchar(64)',
		'NotificationText' => 'Text',
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab('Root.Main', new TextField('Subject', _t('NotifyUsersWorkflowAction.SUBJECT', 'Subject')));
		$fields->addFieldToTab('Root.Main', new TextareaField('NotificationText', _t('NotifyUsersWorkflowAction.MESSAGE', 'Message')));
		return $fields;
	}

	public function execute() {
		// send the emails to everyone

		return true;
	}
}