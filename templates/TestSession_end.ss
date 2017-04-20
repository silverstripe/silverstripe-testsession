<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<% base_tag %>
		$MetaTags
		<% require css('framework/client/dist/styles/debug.css') %>
		<% require css('testsession/client/styles/styles.css') %>
	</head>
	<body>
		<div class="info">
			<h1>SilverStripe TestSession</h1>
		</div>
		<div class="content">
			<p>Test session ended.</p>
			<ul>
				<li>
					<a id="home-link" href="$BaseHref">Return to your site</a>
				</li>
				<li>
					<a id="start-link" href="$Link">Start a new test session</a>
				</li>
			</ul>
		</div>
	</body>
</html>
