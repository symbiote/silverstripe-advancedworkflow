<div id="ModelAdminPanel">
	<% if EditForm %>
		$EditForm
	<% else %>
		<form id="Form_EditForm" action="#" method="post">
			<h1>$ApplicationName</h1>
			<p><% _t('SELECTWORKFLOW', 'Please select a workflow to view.') %></p>
		</form>
	<% end_if %>
</div>
<p id="statusMessage" style="visibility: hidden"></p>