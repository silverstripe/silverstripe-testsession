<% if State %>
<p>
	Current testing state
<ul>
	<% loop State %>
	<li><strong>$Name:</strong> $Value</li>
	<% end_loop %>
</ul>
</p>
<% end_if %>