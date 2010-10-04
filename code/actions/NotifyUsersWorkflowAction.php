<?php
/**
 * A workflow action that notifies users attached to the workflow path that they have a task awaiting them.
 *
 * @todo       Show the CMS user what fields are available in the email.
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    activityworkflow
 * @subpackage actions
 */
class NotifyUsersWorkflowAction extends WorkflowAction {

	public static $db = array(
		'EmailSubject'  => 'Varchar(100)',
		'EmailFrom'     => 'Varchar(50)',
		'EmailTemplate' => 'Text'
	);

	public static $icon = 'activityworkflow/images/notify.png';

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->addFieldsToTab('Root.Main', array(
			new HeaderField('NotificationEmail', $this->fieldLabel('NotificationEmail')),
			new LiteralField('NotificationNote', '<p>' . $this->fieldLabel('NotificationNote') . '</p>'),
			new TextField('EmailSubject', $this->fieldLabel('EmailSubject')),
			new TextField('EmailFrom', $this->fieldLabel('EmailFrom')),
			new TextareaField('EmailTemplate', $this->fieldLabel('EmailTemplate'))
		));

		return $fields;
	}

	public function fieldLabels() {
		return array_merge(parent::fieldLabels(), array(
			'NotificationEmail' => _t('ActivityWorkfow.NOTIFICATIONEMAIL', 'Notification Email'),
			'NotificationNote'  => _t('ActivityWorkfow.NOTIFICATIONNOTE',
				'All users attached to the workflow will be sent an email when this action is run.'),
			'EmailSubject'      => _t('ActivityWorkfow.EMAILSUBJECT', 'Email subject'),
			'EmailFrom'         => _t('ActivityWorkfow.EMAILFROM', 'Email from'),
			'EmailTemplate'     => _t('ActivityWorkfow.EMAILTEMPLATE', 'Email template')
		));
	}

	public function execute() {
		$email   = new Email;
		$members = $this->Workflow()->getAssignedMembers();
		$emails  = '';

		if(!$members || !count($members)) return;

		foreach($members as $member) {
			if($member->Email) $emails .= "$member->Email, ";
		}

		$context   = $this->getContextFields();
		$member    = $this->getMemberFields();
		$variables = array();

		foreach($context as $field => $val) $variables["\$Context.$field"] = $val;
		foreach($member as $field => $val)  $variables["\$Member.$field"] = $val;

		$subject = str_replace(array_keys($variables), array_values($variables), $this->EmailSubject);
		$body    = str_replace(array_keys($variables), array_values($variables), $this->EmailTemplate);

		$email->setSubject($subject);
		$email->setFrom($this->EmailFrom);
		$email->setBcc(substr($emails, 0, -2));
		$email->setBody($body);
		$email->send();

		return true;
	}

	/**
	 * @return array
	 */
	public function getContextFields() {
		if(!$this->Workflow()->getContext()) return array();

		$record = $this->Workflow()->getContext();
		$fields = $record->summaryFields();
		$result = array();

		foreach($fields as $field) {
			$result[$field] = $record->$field;
		}

		if($record instanceof SiteTree) {
			$result['CMSLink'] = singleton('CMSMain')->Link("show/{$record->ID}");
		}

		return $result;
	}

	/**
	 * @return array
	 */
	public function getMemberFields() {
		$member = Member::currentUser();
		$result = array();

		if($member) foreach($member->summaryFields() as $field => $title) {
			$result[$field] = $member->$field;
		}

		if($member && !array_key_exists('Name', $result)) {
			$result['Name'] = $member->getName();
		}

		return $result;
	}

}