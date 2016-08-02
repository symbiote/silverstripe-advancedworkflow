<% loop $WorkflowMessages %>
    <div class="message $Style">
        <h2>$Title</h2>

        <p>$Author <% _t('WorkflowMessage_ss.AUTHOR_ACTION', 'has saved the following dates') %></p>

        <% if $Error %><p>$Error</p><% end_if %>

        <% if $DatePublish %>
            <p><strong>$DatePrefix <% _t('WorkflowMessage_ss.PUBLISH_DATE', 'publish date') %></strong> &mdash; $DatePublish</p>
        <% end_if %>
        <% if $DateUnPublish %>
            <p><strong>$DatePrefix <% _t('WorkflowMessage_ss.UNPUBLISH_DATE', 'expiry date') %></strong> &mdash; $DateUnPublish</p>
        <% end_if %>

        <% if $Approver %>
            <p>$Approver <% _t('WorkflowMessage_ss.APPROVER_ACTION', 'has approved this change.') %></p>
        <% end_if %>
    </div>
<% end_loop %>
