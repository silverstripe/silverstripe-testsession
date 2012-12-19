<?php
class TestSessionController extends Controller {

	static $allowed_actions = array(
		'index',
		'start',
		'end',
		'setdb',
		'emptydb',
	);

	public function init() {
		parent::init();
		
		$canAccess = (Director::isDev() || Director::is_cli() || Permission::check("ADMIN"));
		if(!$canAccess) return Security::permissionFailure($this);
	}
	
	/**
	 * Start a test session.
	 * Usage: visit dev/testsession/start?fixture=(fixturefile).  A test database will be constructed, and your
	 * browser session will be amended to use this database.  This can only be run on dev and test sites.
	 *
	 * See {@link setdb()} for an alternative approach which just sets a database
	 * name, and is used for more advanced use cases like interacting with test databases
	 * directly during functional tests.
	 *
	 * Requires PHP's mycrypt extension in order to set the database name
	 * as an encrypted cookie.
	 */
	public function start() {
		if(!Director::isLive()) {
			if(SapphireTest::using_temp_db()) {
				$endLink = Director::baseURL() . "dev/testsession/end";
				return "<p><a id=\"end-session\" href=\"$endLink\">You're in the middle of a test session;"
					. " click here to end it.</a></p>";
			
			} else if(!isset($_GET['fixture'])) {
				$me = Director::baseURL() . "dev/testsession/start";
				return <<<HTML
<form action="$me">				
	<p>Enter a fixture file name to start a new test session.  Don't forget to visit dev/testsession/end when
	you're done!</p>
	<p>Fixture file (leave blank to start with default set-up): <input id="fixture-file" name="fixture" /></p>
	<input type="hidden" name="flush" value="1">
	<p><input id="start-session" value="Start test session" type="submit" /></p>
</form>
HTML;
			} else {
				$fixtureFile = $_GET['fixture'];
				
				if($fixtureFile) {
					// Validate fixture file
					$realFile = realpath(BASE_PATH.'/'.$fixtureFile);
					$baseDir = realpath(Director::baseFolder());
					if(!$realFile || !file_exists($realFile)) {
						return "<p>Fixture file doesn't exist</p>";
					} else if(substr($realFile,0,strlen($baseDir)) != $baseDir) {
						return "<p>Fixture file must be inside $baseDir</p>";
					} else if(substr($realFile,-4) != '.yml') {
						return "<p>Fixture file must be a .yml file</p>";
					} else if(!preg_match('/^([^\/.][^\/]+)\/tests\//', $fixtureFile)) {
						return "<p>Fixture file must be inside the tests subfolder of one of your modules.</p>";
					}
				}

				$dbname = SapphireTest::create_temp_db();

				DB::set_alternative_database_name($dbname);
				
				// Fixture
				if($fixtureFile) {
					$fixture = Injector::inst()->create('YamlFixture', $fixtureFile);
					$fixture->saveIntoDatabase();
					
				// If no fixture, then use defaults
				} else {
					$dataClasses = ClassInfo::subclassesFor('DataObject');
					array_shift($dataClasses);
					foreach($dataClasses as $dataClass) singleton($dataClass)->requireDefaultRecords();
				}
				
				return "<p>Started testing session with fixture '$fixtureFile'.
					Time to start testing; where would you like to start?</p>
					<ul>
						<li><a id=\"home-link\" href=\"" .Director::baseURL() . "\">Homepage - published site</a></li>
						<li><a id=\"draft-link\" href=\"" .Director::baseURL() . "?stage=Stage\">Homepage - draft site
							</a></li>
						<li><a id=\"admin-link\" href=\"" .Director::baseURL() . "admin/\">CMS Admin</a></li>
						<li><a id=\"end-link\" href=\"" .Director::baseURL() . "dev/testsession/end\">
							End your test session</a></li>
					</ul>";
			}
						
		} else {
			return "<p>startession can only be used on dev and test sites</p>";
		}
	}

	/**
	 * Set an alternative database name in the current browser session as a cookie.
	 * Useful for functional testing libraries like behat to create a "clean slate". 
	 * Does not actually create the database, that's usually handled
	 * by {@link SapphireTest::create_temp_db()}.
	 *
	 * The database names are limited to a specific naming convention as a security measure:
	 * The "ss_tmpdb" prefix and a random sequence of seven digits.
	 * This avoids the user gaining access to other production databases 
	 * available on the same connection.
	 *
	 * See {@link start()} for a different approach which actually creates
	 * the DB and loads a fixture file instead.
	 *
	 * Requires PHP's mycrypt extension in order to set the database name
	 * as an encrypted cookie.
	 */
	public function setdb() {
		if(Director::isLive()) {
			return $this->httpError(403, "dev/testsession/setdb can only be used on dev and test sites");
		}
		if(!isset($_GET['database'])) {
			return $this->httpError(400, "dev/testsession/setdb must be used with a 'database' parameter");
		}
		
		$name = $_GET['database'];
		$prefix = defined('SS_DATABASE_PREFIX') ? SS_DATABASE_PREFIX : 'ss_';
		$pattern = strtolower(sprintf('#^%stmpdb\d{7}#', $prefix));
		if($name && !preg_match($pattern, $name)) {
			return $this->httpError(400, "Invalid database name format");
		}

		DB::set_alternative_database_name($name);

		if($name) {
			return "<p>Set database session to '$name'.</p>";
		} else {
			return "<p>Unset database session.</p>";
		}
		
	}
	
	public function emptydb() {
		if(SapphireTest::using_temp_db()) {
			SapphireTest::empty_temp_db();

			if(isset($_GET['fixture']) && ($fixtureFile = $_GET['fixture'])) {
				$fixture = Injector::inst()->create('YamlFixture', $fixtureFile);
				$fixture->saveIntoDatabase();
				return "<p>Re-test the test database with fixture '$fixtureFile'.  Time to start testing; where would"
					. " you like to start?</p>";

			} else {
				return "<p>Re-test the test database.  Time to start testing; where would you like to start?</p>";
			}
			
		} else {
			return "<p>dev/testsession/emptydb can only be used with a temporary database. Perhaps you should use"
				. " dev/testsession/start first?</p>";
		}
	}
	
	public function end() {
		SapphireTest::kill_temp_db();
		DB::set_alternative_database_name(null);

		return "<p>Test session ended.</p>
			<ul>
				<li><a id=\"home-link\" href=\"" .Director::baseURL() . "\">Return to your site</a></li>
				<li><a id=\"start-link\" href=\"" .Director::baseURL() . "dev/testsession/start\">
					Start a new test session</a></li>
			</ul>";
	}

}