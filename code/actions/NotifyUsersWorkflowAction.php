<?php
/**
 * A workflow action that notifies users attached to the workflow path that they have a task awaiting them.
 *
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
			new TextareaField('EmailTemplate', $this->fieldLabel('EmailTemplate'), 10),
			new ToggleCompositeField('FormattingHelpContainer',
				$this->fieldLabel('FormattingHelp'), new LiteralField('FormattingHelp', $this->getFormattingHelp()))
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
			'EmailTemplate'     => _t('ActivityWorkfow.EMAILTEMPLATE', 'Email template'),
			'FormattingHelp'    => _t('ActivityWorkflow.FORMATTINGHELP', 'Formatting Help')
		));
	}

	public function execute(WorkflowInstance $workflow) {
		$email   = new Email;
		$members = $workflow->getAssignedMembers();
		$emails  = '';

		if(!$members || !count($members)) return;

		foreach($members as $member) {
			if($member->Email) $emails .= "$member->Email, ";
		}

		$context   = $this->getContextFields($workflow->getTarget());
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
	 * @param  DataObject $target
	 * @return array
	 */
	public function getContextFields(DataObject $target) {
		$fields = $target->summaryFields();
		$result = array();

		foreach($fields as $field) {
			$result[$field] = $target->$field;
		}

		if($target instanceof SiteTree) {
			$result['CMSLink'] = singleton('CMSMain')->Link("show/{$target->ID}");
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

	/**
	 * Returns a basic set of instructions on how email templates are populated with variables.
	 *
	 * @return string
	 */
	public function getFormattingHelp() {
		$note = _t('NotifyUsersWorkflowAction.FORMATTINGNOTE',
			'Notification emails can contain HTML formatting. The following special variables are replaced with their
			respective values in both the email subject and template/body.');
		$member = _t('NotifyUsersWorkflowAction.MEMBERNOTE',
			'These fields will be populated from the member that initiates the notification action.');
		$context = _t('NotifyUsersWorkflowAction.CONTEXTNOTE',
			'Any summary fields from the workflow target will be available. Additionally, the CMSLink variable will
			contain a link to edit the workflow target in the CMS (if it is a SiteTree object).');
		$fieldName = _t('NotifyUsersWorkflowAction.FIELDNAME', 'Field name');

		$memberFields = implode(', ', array_keys($this->getMemberFields()));

		return "<p>$note</p>
			<p><strong>\$Member.($memberFields)</strong><br>$member</p>
			<p><strong>\$Context.($fieldName)</strong><br>$context</p>";
	}

}