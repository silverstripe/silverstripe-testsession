<% if $State %>
<p>
	<a href="#" onclick="document.getElementById('state').style.display = 'block'; return false;">Show testing state</a>
	<ul id="state" style="display: none;">
		<% loop $State %>
		    <li><strong>$Name:</strong> $Value</li>
		<% end_loop %>
	</ul>
</p>
<% end_if %>
