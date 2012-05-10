
<% if UserWorkflows %>

<% require javascript(advancedworkflow/javascript/advancedworkflow-management.js) %>

<table class="userWorkflowTable">
	<thead>
		<tr>
			<th width="45%">Item</th>
			<th width="15%">Edited by</th>
			<th width="15%">Submitted</th>
			<th width="25%">Actions</th>
		</tr>
	</thead>
	<tbody>
<% control UserWorkflows %>
	<tr>
		<td><a href="$Target.Link?stage=Stage">$Target.Title.XML</a></td>
		<td>$Initiator.getTitle</td>
		<td>$Created.Nice</td>
		<td>
			<% if validTransitions %>
			<ul data-instance-id="$ID" class="workflowActionsList">
				<% control validTransitions %>
				<li><a href="#" class="advancedWorkflowTransition" data-transition-id="$ID">$Title.XML</a></li>
				<% end_control %>
			</ul>
			<% end_if %>
		</td>
	</tr>
<% end_control %>
	</tbody>

</table>
<% end_if %>