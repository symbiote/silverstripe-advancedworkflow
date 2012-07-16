<% if PastActions %>
	---<br>
	Comment history on this request:<br>
	<% loop PastActions %><% if Comment %>[$Created.Nice] <strong>$Member.FirstName $Member.Surname</strong>: $Comment<br><% end_if %><% end_loop %>
<% end_if %>
