<% loop $WorkflowMessages %>
    <div class="message $Style">
        <h2>$Title</h2>

        <% if $Approver %>
            <p>$Approver <% _t('WorkflowMessage_ss.APPROVER_ACTION', 'has approved this change.') %></p>
        <% end_if %>
        
        <p>$Author <% _t('WorkflowMessage_ss.AUTHOR_ACTION', 'has saved the following dates') %></p>
        <p><strong>$DatePrefix <% _t('WorkflowMessage_ss.PUBLISH_DATE', 'publish date') %></strong> &mdash; $DatePublish</p>
        <p><strong>$DatePrefix <% _t('WorkflowMessage_ss.UNPUBLISH_DATE', 'expiry date') %></strong> &mdash; $DateUnPublish</p>
    </div>
<% end_loop %>
