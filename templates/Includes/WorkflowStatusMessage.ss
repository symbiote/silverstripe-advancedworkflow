<% with $WorkflowMessage %>
	<div class="message $Style">
		<h2>$Title</h2>
		<p>$Author <% _t('WorkflowMessage_ss.AUTHOR_ACTION', 'has saved the following dates in draft') %></p>
		<p><strong>$DatePrefix <% _t('WorkflowMessage_ss.PUBLISH_DATE', 'publish date') %></strong> &mdash; $DatePublish<br>
		<p><strong>$DatePrefix <% _t('WorkflowMessage_ss.UNPUBLISH_DATE', 'expiry date') %></strong> &mdash; $DateUnPublish</p>
	</div>
<% end_with %>
