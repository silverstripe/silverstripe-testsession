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
			<p>
				You're in the middle of a test session. 
				<a id="end-session" href="$Link(end)">Click here to end it.</a>
			</p>
			<% include TestSession_State %>
		</div>
	</body>
</html>


