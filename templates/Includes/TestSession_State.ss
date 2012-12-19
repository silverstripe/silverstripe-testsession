<% if State %>
<p>
	Current testing state
<ul>
	<% control State %>
	<li><strong>$Name:</strong> $Value</li>
	<% end_control %>
</ul>
</p>
<% end_if %>