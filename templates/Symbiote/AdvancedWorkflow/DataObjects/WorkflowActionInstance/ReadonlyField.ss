<%-- Modified from silverstripe/admin/themes/cms-forms/templates/SilverStripe/Forms/ReadonlyField.ss to change p to div --%>
<div id="$ID" tabIndex="0" class="form-control-static<% if $extraClass %> $extraClass<% end_if %>" <% include SilverStripe/Forms/AriaAttributes %>>
    $Value
</div>
<% if $IncludeHiddenField %>
    <input $getAttributesHTML("id", "type") id="hidden-{$ID}" type="hidden" />
<% end_if %>
