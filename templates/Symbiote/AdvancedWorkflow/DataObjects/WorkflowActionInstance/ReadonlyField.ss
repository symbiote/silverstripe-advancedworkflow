<%-- Modified from silverstripe/admin/themes/cms-forms/templates/SilverStripe/Forms/ReadonlyField.ss to change p to div --%>
<div id="$ID"
     tabindex="0"
     class="form-control-static<% if $extraClass %> $extraClass<% end_if %>"
     role="textbox"
     aria-readonly="true"
     <% include SilverStripe/Forms/AriaAttributes %>
>$FormattedValue</div>
<% if $IncludeHiddenField %>
    <input $getAttributesHTML("id", "type") id="hidden-{$ID}" type="hidden" />
<% end_if %>
