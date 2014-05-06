<!-- SUCCESS: DBNAME=$DatabaseName -->
<p>
	Test session in progress.
	<a id="end-session" href="$Link(end)">Click here to end it.</a>
</p>
<p>Where would you like to start?</p>
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
</ul>
<% include TestSession_State %>
$ProgressForm