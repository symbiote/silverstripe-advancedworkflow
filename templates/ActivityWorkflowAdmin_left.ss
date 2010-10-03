<% require javascript(activityworkflow/thirdparty/jquery-jstree/jquery.jstree.js) %>
<% require javascript(activityworkflow/javascript/ActivityWorkflowAdmin.js) %>
<% require css(activityworkflow/css/ActivityWorkflowAdmin.css) %>

<h2><% _t('WORKFLOWS', 'Workflows') %></h2>

$CreateDefinitionForm
<div id="ToggleForms">
	$CreateActionForm
	$CreateTransitionForm
</div>

<div id="Workflows" href="$Link(tree)" data-href-sort="$Link(sort)"></div>