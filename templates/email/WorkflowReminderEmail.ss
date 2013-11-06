<p>
	<%t WorkflowReminderEmail.Content "This is an automated reminder that the workflow '{title}' you are currently
	assigned to has not been actioned for {days} days. The
	workflow was last updated on {date} at {time}." title=$Instance.Title days=$Instance.Definition.RemindDays date=$Instance.LastEdited.Date time=$Instance.LastEdited.Time %>
</p>

<% if Link %>
	<p><a href="$Link">
		<%t WorkflowReminderEmail.Action "Click here to action this workflow" %>.
	</a></p>
<% end_if %>