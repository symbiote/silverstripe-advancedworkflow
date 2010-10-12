<% require javascript(advancedworkflow/thirdparty/jquery-jstree/jquery.jstree.js) %>
<% require javascript(advancedworkflow/javascript/AdvancedWorkflowAdmin.js) %>
<% require css(advancedworkflow/css/AdvancedWorkflowAdmin.css) %>

<h2><% _t('WORKFLOWS', 'Workflows') %></h2>

$CreateDefinitionForm
<div id="ToggleForms">
	$CreateActionForm
	$CreateTransitionForm
</div>

<div id="Workflows" href="$Link(tree)" data-href-sort="$Link(sort)"></div>