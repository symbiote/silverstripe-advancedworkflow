<div id="$ID" class="field workflow-field" data-sort-link="$Link('sort')" data-securityid="$SecurityID">
	<div class="workflow-field-dialog"></div>
	<div class="workflow-field-loading"></div>

	<div class="workflow-field-header">
		<h3>$Title</h3>
		<div class="workflow-field-create">
			<select class="workflow-field-create-class no-change-track">
				<option value="">
					<%t WorkflowField.CreateAction "Create an action" %>&hellip;
				</option>
				<% loop $CreateableActions %>
					<option value="$Top.ActionLink('new',$Class,'edit')">$Title</option>
				<% end_loop %>
			</select>
			<button class="ss-ui-button ui-state-disabled workflow-field-do-create" data-icon="add">
				<%t WorkflowField.CreateLabel "Create" %>
			</button>
		</div>
	</div>
	<div class="workflow-field-actions" data-href-order="$Link(order)">
		<% loop $Definition.Actions %>
			<div class="workflow-field-action" data-id="$ID">
				<div class="workflow-field-action-header">
					<div class="workflow-field-action-drag"></div>
					<img src="$Icon" alt="$Title.ATT" class="workflow-field-action-icon">

					<h4>$Title</h4>

					<div class="workflow-field-action-buttons">
						<a class="ss-ui-button workflow-field-open-dialog<% if $canEdit %><% else %> workflow-field-action-disabled<% end_if %>" href="$Top.ActionLink('item',$ID,'edit')" data-icon="pencil">
							<%t WorkflowField.EditAction "Edit" %>
						</a>
						<a class="ss-ui-button workflow-field-open-dialog <% if $canAddTransition %><% else %> workflow-field-action-disabled<% end_if %>" href="$Top.TransitionLink('new',$ID,'edit')" data-icon="chain--arrow">
							<%t WorkflowField.AddTransitionAction "Add Transition" %>
						</a>
						<a href="$Top.ActionLink('item',$ID,'delete')" data-securityid="$SecurityID" class="ss-ui-button workflow-field-delete<% if $canDelete %><% else %> workflow-field-action-disabled<% end_if %>" data-icon="cross-circle">
							<%t WorkflowField.DeleteAction "Delete" %>
						</a>
					</div>
				</div>

				<% if $Transitions %>
					<ol class="workflow-field-action-transitions">
						<% loop $Transitions %>
							<li data-id="$ID">
								<div class="workflow-field-action-drag"></div>
								<span class="ui-icon ui-icon-arrowreturnthick-1-e"></span>
								<div class="workflow-field-transition-title">
									<span class="title">$Title</span>
									<span class="ui-icon btn-icon-chain-small"></span>
									<span class="next-title">$NextAction.Title</span>
								</div>
								<a href="$Top.TransitionLink('item',$ID,'edit')" class="ui-icon btn-icon-pencil workflow-field-open-dialog<% if $canEdit %><% else %> workflow-field-action-disabled<% end_if %>">
									<%t WorkflowField.EditAction "Edit" %>
								</a>
								<a href="$Top.TransitionLink('item',$ID,'delete')" data-securityid="$SecurityID" class="ui-icon btn-icon-cross-circle workflow-field-delete<% if $canDelete %><% else %> workflow-field-action-disabled<% end_if %>">
									<%t WorkflowField.DeleteAction "Delete" %>
								</a>
							</li>
						<% end_loop %>
					</ol>
				<% end_if %>
			</div>
		<% end_loop %>
	</div>
</div>