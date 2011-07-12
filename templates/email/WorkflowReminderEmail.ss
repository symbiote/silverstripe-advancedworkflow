<p>
	This is an automated reminder that the workflow "$Instance.Title" you are currently
	assigned to has not been actioned for $Instance.Definition.RemindDays days. The
	workflow was last updated on $Instance.LastEdited.Date at $Instance.LastEdited.Time.
</p>

<% if Link %>
	<p><a href="$Link">Click here to action this workflow.</a></p>
<% end_if %>