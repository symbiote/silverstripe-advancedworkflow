<div id="form_actions_right" class="ajaxActions">
</div>

<% if EditForm %>
	$EditForm
<% else %>
	<form id="Form_EditForm" action="admin/workflowadmin?executeForm=EditForm" method="post" enctype="multipart/form-data">
		<p><% _t('SELECT_WORKFLOW', 'Select a workflow to view') %></p>
	</form>
<% end_if %>

<p id="statusMessage" style="visibility:hidden"></p>
