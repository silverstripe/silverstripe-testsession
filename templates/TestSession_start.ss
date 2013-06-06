<!-- SUCCESS: DBNAME=$DatabaseName -->
<p>
	Started testing session. 
	<% if Fixture %>Loaded fixture "$Fixture" into database.<% end_if %>
	Time to start testing; where would you like to start?
</p>
<ul>
	<li>
		<a id="home-link" href="$BaseHref">Homepage - published site</a>
	</li>
	<li>
		<a id="draft-link" href="$BaseHref/?stage=Stage">Homepage - draft site</a>
	</li>
	<li>
		<a id="admin-link" href="$BaseHref/admin/">CMS Admin</a>
	</li>
	<li>
		<a id="end-link" href="$Link(end)">End your test session</a>
 </li>
</ul>
<% include TestSession_State %>
<form action="$Link(set)">				
	<p>
		Enter a fixture file name to add it to the test session.  
		Don't forget to visit dev/testsession/end when you're done!
	</p>
	<p>
		Fixture file: 
		<input id="fixture-file" name="fixture" />
	</p>
	<input type="hidden" name="flush" value="1">
	<p>
		<input id="start-session" value="Start test session" type="submit" />
	</p>
</form>