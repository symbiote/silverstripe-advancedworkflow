<div id="$ID" class="field workflow-field" data-sort-link="$Link('sort')" data-securityid="$SecurityID">
	<div class="workflow-field-dialog"></div>
	<div class="workflow-field-loading"></div>

	<div class="workflow-field-header">
		<h3>$Title</h3>
		<div class="workflow-field-create">
			<select class="workflow-field-create-class no-change-track">
				<option value="">Create an action&hellip;</option>
				<% loop $CreateableActions %>
					<option value="$Top.Link("action")/new/$Class/edit">$Title</option>
				<% end_loop %>
			</select>
			<button class="ss-ui-button ui-state-disabled workflow-field-do-create" data-icon="add">
				Create
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
						<a class="ss-ui-button workflow-field-open-dialog<% if $disableButton %> workflow-field-action-disabled<% end_if %>" href="$Top.Link("action")/item/$ID/edit" data-icon="pencil">
							Edit
						</a>
						<a class="ss-ui-button workflow-field-open-dialog<% if $disableButtonAddTransition %> workflow-field-action-disabled<% end_if %>" href="$Top.Link("transition")/new/$ID/edit" data-icon="chain--arrow">
							Add Transition
						</a>
						<a href="$Top.Link("action")/item/$ID/delete" data-securityid="$SecurityID" class="ss-ui-button workflow-field-delete<% if $disableButton %> workflow-field-action-disabled<% end_if %>" data-icon="cross-circle">
							Delete
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
								<a href="$Top.Link("transition")/item/$ID/edit" class="ui-icon btn-icon-pencil workflow-field-open-dialog<% if $disableButton %> workflow-field-action-disabled<% end_if %>">Edit</a>
								<a href="$Top.Link("transition")/item/$ID/delete" data-securityid="$SecurityID" class="ui-icon btn-icon-cross-circle workflow-field-delete<% if $disableButton %> workflow-field-action-disabled<% end_if %>">Delete</a>
							</li>
						<% end_loop %>
					</ol>
				<% end_if %>
			</div>
		<% end_loop %>
	</div>
</div>