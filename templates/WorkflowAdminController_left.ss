<h2><% _t('WORKFLOWS', 'Workflows') %></h2>

<div id="treepanes">
	<ul id="TreeActions">
		<li class="action" id="addworkflow"><button><% _t('CREATE','Create',PR_HIGH) %></button></li>
		<li class="action" id="deleteworkflow"><button><% _t('DELETE', 'Delete') %></button></li>
	</ul>
	
	<div style="clear:both;"></div>

	<% control CreateWorkflowForm %>
		<form class="actionparams" id="$FormName" action="$FormAction">
			<% control Fields %>
			$FieldHolder
			<% end_control %>
		</form>
	<% end_control %>

	<div id="WorkflowTree">
	</div>
</div>