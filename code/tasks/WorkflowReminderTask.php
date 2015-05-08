<?php
/**
 * A task that sends a reminder email to users assigned to a workflow that has
 * not been actioned for n days.
 *
 * @package advancedworkflow
 */
class WorkflowReminderTask extends BuildTask {

	protected $title       = 'Workflow Reminder Task';
	protected $description = 'Sends out workflow reminder emails to stale workflow instances';

	public function run($request) {
		$sent = 0;
		if (WorkflowInstance::get()->count()) { // Don't attempt the filter if no instances -- prevents a crash
			$active = WorkflowInstance::get()
					->innerJoin('WorkflowDefinition', '"DefinitionID" = "WorkflowDefinition"."ID"')
					->filter(array('WorkflowStatus' => array('Active', 'Paused'), 'RemindDays:GreaterThan' => '0'));
			$active->filter(array('RemindDays:GreaterThan' => '0'));
			if ($active) foreach ($active as $instance) {
				$edited = strtotime($instance->LastEdited);
				$days   = $instance->Definition()->RemindDays;

				if ($edited + $days * 3600 * 24 > time()) {
					continue;
				}

				$email   = new Email();
				$bcc     = '';
				$members = $instance->getAssignedMembers();
				$target  = $instance->getTarget();

				if (!$members || !count($members)) continue;

				$email->setSubject("Workflow Reminder: $instance->Title");
				$email->setBcc(implode(', ', $members->column('Email')));
				$email->setTemplate('WorkflowReminderEmail');
				$email->populateTemplate(array(
					'Instance' => $instance,
					'Link'     => $target instanceof SiteTree ? "admin/show/$target->ID" : ''
				));

				$email->send();
				$sent++;

				$instance->LastEdited = time();
				$instance->write();
			}
		}
		echo "Sent $sent workflow reminder emails.\n";
	}

}