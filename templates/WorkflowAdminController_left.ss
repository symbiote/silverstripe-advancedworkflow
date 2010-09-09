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

	$DeleteItemsForm

	<div id="sitetree_holder">
		<ul id="sitetree" class="tree unformatted">
			<li id="$ID" class="Root"><a><strong><% _t('WORKFLOWS', 'Workflows') %></strong></a>
				<% if Workflows %>
				<ul>
					<% control Workflows %>
					<li id="record-$ID">
						<a href="admin/workflowadmin/loadworkflow/$ID" title="">$Title</a>
					</li>
					<!-- all other users' changes-->
					<% end_control %>
				</ul>
				<% end_if %>
			</li>
		</ul>
	</div>
</div>