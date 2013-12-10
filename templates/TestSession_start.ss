<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<% base_tag %>
		$MetaTags
		<% require css('framework/css/debug.css') %>
		<% require css('testsession/css/styles.css') %>
	</head>
	<body>
		<div class="info">
			<h1>SilverStripe TestSession</h1>
		</div>
		<div class="content">
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
		</div>
	</body>
</html>