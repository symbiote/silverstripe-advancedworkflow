<?php

use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * A workflow action that notifies users attached to the workflow path that they have a task awaiting them.
 *
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage actions
 */
class NotifyUsersWorkflowAction extends WorkflowAction {

	/**
	 * @var bool Should templates be constrained to just known-safe variables.
	 */
	private static $whitelist_template_variables = false;

	private static $db = array(
		'EmailSubject'			=> 'Varchar(100)',
		'EmailFrom'				=> 'Varchar(50)',
		'EmailTemplate'			=> 'Text'
	);

	private static $icon = 'advancedworkflow/images/notify.png';

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->addFieldsToTab('Root.Main', array(
			new HeaderField('NotificationEmail', $this->fieldLabel('NotificationEmail')),
			new LiteralField('NotificationNote', '<p>' . $this->fieldLabel('NotificationNote') . '</p>'),
			new TextField('EmailSubject', $this->fieldLabel('EmailSubject')),
			new TextField('EmailFrom', $this->fieldLabel('EmailFrom')),

			new TextareaField('EmailTemplate', $this->fieldLabel('EmailTemplate')),
			new ToggleCompositeField('FormattingHelpContainer',
				$this->fieldLabel('FormattingHelp'), new LiteralField('FormattingHelp', $this->getFormattingHelp()))
		));

		$this->extend('updateNotifyUsersCMSFields', $fields);

		return $fields;
	}

	public function fieldLabels($relations = true) {
		return array_merge(parent::fieldLabels($relations), array(
			'NotificationEmail' => _t('NotifyUsersWorkflowAction.NOTIFICATIONEMAIL', 'Notification Email'),
			'NotificationNote'  => _t('NotifyUsersWorkflowAction.NOTIFICATIONNOTE',
				'All users attached to the workflow will be sent an email when this action is run.'),
			'EmailSubject'      => _t('NotifyUsersWorkflowAction.EMAILSUBJECT', 'Email subject'),
			'EmailFrom'         => _t('NotifyUsersWorkflowAction.EMAILFROM', 'Email from'),
			'EmailTemplate'     => _t('NotifyUsersWorkflowAction.EMAILTEMPLATE', 'Email template'),
			'FormattingHelp'    => _t('NotifyUsersWorkflowAction.FORMATTINGHELP', 'Formatting Help')
		));
	}

	public function execute(WorkflowInstance $workflow) {
		$members = $workflow->getAssignedMembers();

		if(!$members || !count($members)) {
			return true;
		}

		$member = Member::currentUser();
		$initiator = $workflow->Initiator();

		$contextFields   = $this->getContextFields($workflow->getTarget());
		$memberFields    = $this->getMemberFields($member);
		$initiatorFields = $this->getMemberFields($initiator);

		$variables = array();

		foreach($contextFields as $field => $val) $variables["\$Context.$field"] = $val;
		foreach($memberFields as $field => $val)  $variables["\$Member.$field"] = $val;
		foreach($initiatorFields as $field => $val)  $variables["\$Initiator.$field"] = $val;

		$pastActions = $workflow->Actions()->sort('Created DESC');
		$variables["\$CommentHistory"] = $this->customise(array(
			'PastActions'=>$pastActions,
			'Now'=>DBDatetime::now()
		))->renderWith('CommentHistory');

		$from = str_replace(array_keys($variables), array_values($variables), $this->EmailFrom);
		$subject = str_replace(array_keys($variables), array_values($variables), $this->EmailSubject);

		if ($this->config()->whitelist_template_variables) {
			$item = new ArrayData(array(
				'Initiator' => new ArrayData($initiatorFields),
				'Member' => new ArrayData($memberFields),
				'Context' => new ArrayData($contextFields),
				'CommentHistory' => $variables["\$CommentHistory"]
			));
		}
		else {
			$item = $workflow->customise(array(
				'Items' => $workflow->Actions(),
				'Member' => $member,
				'Context' => new ArrayData($contextFields),
				'CommentHistory' => $variables["\$CommentHistory"]
			));
		}


		$view = SSViewer::fromString($this->EmailTemplate);
		$this->extend('updateView', $view);

		$body = $view->process($item);

		foreach($members as $member) {
			if($member->Email) {
				$email = new Email;
				$email->setTo($member->Email);
				$email->setSubject($subject);
				$email->setFrom($from);
				$email->setBody($body);
				$email->send();
			}
		}

		return true;
	}

	/**
	 * @param  DataObject $target
	 * @return array
	 */
	public function getContextFields(DataObject $target) {
		$result = array();
		if (!$target) {
			return $result;
		}

		$fields = $target->db();
        unset($fields['ID']);

		foreach($fields as $field => $fieldDesc) {
			$result[$field] = $target->$field;
		}

		if($target instanceof CMSPreviewable) {
			$result['CMSLink'] = $target->CMSEditLink();
		} else if ($target->hasMethod('WorkflowLink')) {
			$result['CMSLink'] = $target->WorkflowLink();
		}

		return $result;
	}

	/**
	 * Builds an array with the member information
	 * @param Member $member An optional member to use. If null, will use the current logged in member
	 * @return array
	 */
	public function getMemberFields(Member $member = null) {
		if (!$member){
			$member = Member::currentUser();
		}
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
			respective values in the email subject, email from and template/body.');
		$member = _t('NotifyUsersWorkflowAction.MEMBERNOTE',
			'These fields will be populated from the member that initiates the notification action. For example,
			{$Member.FirstName}.');
		$initiator = _t('NotifyUsersWorkflowAction.INITIATORNOTE',
			'These fields will be populated from the member that initiates the workflow request. For example,
			{$Initiator.Email}.');
		$context = _t('NotifyUsersWorkflowAction.CONTEXTNOTE',
			'Any summary fields from the workflow target will be available. For example, {$Context.Title}.
			Additionally, the {$Context.AbsoluteEditLink} variable will contain a link to edit the workflow target in
			the CMS (if it is a Page), and the {$Context.LinkToPendingItems} variable will generate a link to the CMS\' workflow admin,
			useful for allowing users to enact workflow transitions, directly from emails.');
		$fieldName = _t('NotifyUsersWorkflowAction.FIELDNAME', 'Field name');
		$commentHistory = _t('NotifyUsersWorkflowAction.COMMENTHISTORY', 'Comment history up to this notification.');

		$memberFields = implode(', ', array_keys($this->getMemberFields()));

		return "<p>$note</p>
			<p><strong>{\$Member.($memberFields)}</strong><br>$member</p>
			<p><strong>{\$Initiator.($memberFields)}</strong><br>$initiator</p>
			<p><strong>{\$Context.($fieldName)}</strong><br>$context</p>
			<p><strong>{\$CommentHistory}</strong><br>$commentHistory</p>";
	}

}
