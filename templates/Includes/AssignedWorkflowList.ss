
<% if UserWorkflows %>

<% require javascript(advancedworkflow/javascript/advancedworkflow-management.js) %>

<table class="userWorkflowTable">
	<thead>
		<tr>
			<th width="45%"><%t WorkflowList.TableHeaderItem "Item" %></th>
			<th width="15%"><%t WorkflowList.TableHeaderEditedBy "Edited by" %></th>
			<th width="15%"><%t WorkflowList.TableHeaderSubmitted "Submitted" %></th>
			<th width="25%"><%t WorkflowList.TableHeaderActions "Actions" %></th>
		</tr>
	</thead>
	<tbody>
<% loop UserWorkflows %>
	<tr>
		<td><a href="$Target.Link?stage=Stage">$Target.Title.XML</a></td>
		<td>$Initiator.getTitle</td>
		<td>$Created.Nice</td>
		<td>
			<% if validTransitions %>
			<ul data-instance-id="$ID" class="workflowActionsList">
				<% loop validTransitions %>
				<li><a href="#" class="advancedWorkflowTransition" data-transition-id="$ID">$Title.XML</a></li>
				<% end_loop %>
			</ul>
			<% end_if %>
		</td>
	</tr>
<% end_loop %>
	</tbody>

</table>
<% end_if %>