<?php

/**
 * Responsible for starting and finalizing test sessions.
 * Since these session span across multiple requests, session information is persisted
 * in a file. This file is stored in the webroot by default, and the test session
 * is considered "in progress" as long as this file exists.
 *
 * This allows for cross-request, cross-client sharing of the same testsession,
 * for example: Behat CLI starts a testsession, then opens a web browser which
 * makes a separate request picking up the same testsession.
 *
 * An environment can have an optional identifier ({@link id}), which allows
 * multiple environments to exist at the same time in the same webroot.
 * This enables parallel testing with (mostly) isolated state. 
 *
 * For a valid test session to exist, this needs to contain at least:
 *  - database: The alternate database name that is being used for this test session (e.g. ss_tmpdb_1234567)
 * It can optionally contain other details that should be passed through many separate requests:
 *  - datetime: Mocked SS_DateTime ({@see TestSessionRequestFilter})
 *  - mailer: Mocked Email sender ({@see TestSessionRequestFilter})
 *  - stubfile: Path to PHP stub file for setup ({@see TestSessionRequestFilter})
 * Extensions of TestSessionEnvironment can add extra fields in here to be saved and restored on each request.
 *
 * See {@link $state} for default information stored in the test session.
 */
class TestSessionEnvironment extends Object {
	
	/**
	 * @var int Optional identifier for the session.
	 */
	protected $id;

	/**
	 * @var string The original database name, before we overrode it with our tmpdb.
	 *
	 * Used in {@link self::resetDatabaseName()} when we want to restore the normal DB connection.
	 */
	private $oldDatabaseName;

	/**
	 * @config
	 * @var string Path (from web-root) to the test state file that indicates a testsession is in progress.
	 * Defaults to value stored in testsession/_config/_config.yml
	 */
	private static $test_state_file = 'TESTS_RUNNING.json';

	/**
	 * @config
	 * @var [type]
	 */
	private static $test_state_id_file = 'TESTS_RUNNING-%s.json';

	public function __construct($id = null) {
		parent::__construct();

		if($id) {
			$this->id = $id;
		} else {
			Session::start();
			$this->id = Session::get('TestSessionId');
		}
	}

	/**
	 * @return String Absolute path to the file persisting our state.
	 */
	public function getFilePath() {
		if($this->id) {
			$path = Director::getAbsFile(sprintf($this->config()->test_state_id_file, $this->id));	
		} else {
			$path = Director::getAbsFile($this->config()->test_state_file);	
		}

		return $path;
	}

	/**
	 * Tests for the existence of the file specified by $this->test_state_file
	 */
	public function isRunningTests() {
		return(file_exists($this->getFilePath()));
	}

	/**
	 * @param String $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * @return String
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Creates a temp database, sets up any extra requirements, and writes the state file. The database will be
	 * connected to as part of {@link self::applyState()}, so if you're continuing script execution after calling this
	 * method, be aware that the database will be different - so various things may break (e.g. administrator logins
	 * using the SS_DEFAULT_USERNAME / SS_DEFAULT_PASSWORD constants).
	 *
	 * If something isn't explicitly handled here, and needs special handling, then it should be taken care of by an
	 * extension to TestSessionEnvironment. You can either extend onBeforeStartTestSession() or
	 * onAfterStartTestSession(). Alternatively, for more fine-grained control, you can also extend
	 * onBeforeApplyState() and onAfterApplyState(). See the {@link self::applyState()} method for more.
	 *
	 * @param array $state An array of test state options to write.
	 */
	public function startTestSession($state = null, $id = null) {
		if(!$state) $state = array();
		$this->removeStateFile();
		$this->id = $id;

		$extendedState = $this->extend('onBeforeStartTestSession', $state);

		// $extendedState is now a multi-dimensional array (if extensions exist)
		if($extendedState && is_array($extendedState)) {
			foreach($extendedState as $stateVal) {
				// $stateVal is one extension's additions to $state
				$state = array_merge($state, $stateVal); // Merge this into the original $state
			}
		}

		// Convert to JSON and back so we can share the applyState() code between this and ->loadFromFile()
		$json = json_encode($state, JSON_FORCE_OBJECT);
		$state = json_decode($json);

		$this->applyState($state);

		$this->extend('onAfterStartTestSession');
	}

	public function updateTestSession($state) {
		$this->extend('onBeforeUpdateTestSession', $state);

		// Convert to JSON and back so we can share the appleState() code between this and ->loadFromFile()
		$json = json_encode($state, JSON_FORCE_OBJECT);
		$state = json_decode($json);

		$this->applyState($state);

		$this->extend('onAfterUpdateTestSession');
	}

	/**
	 * Assumes the database has already been created in startTestSession(), as this method can be called from
	 * _config.php where we don't yet have a DB connection.
	 *
	 * Persists the state to the filesystem.
	 *
	 * You can extend this by creating an Extension object and implementing either onBeforeApplyState() or
	 * onAfterApplyState() to add your own test state handling in.
	 *
	 * @throws LogicException
	 * @throws InvalidArgumentException
	 */
	public function applyState($state) {
		$this->extend('onBeforeApplyState', $state);

		$database = (isset($state->database)) ? $state->database : null;

		// back up source
		global $databaseConfig;
		$this->oldDatabaseName = $databaseConfig['database'];

		// Load existing state from $this->state into $state, if there is any
		$oldState = $this->getState();

		if($oldState) {
			foreach($oldState as $k => $v) {
				if(!isset($state->$k)) {
					$state->$k = $v; // Don't overwrite stuff in $state, as that's the new state
				}
			}
		}

		// ensure we have a connection to the database
  		if(isset($state->database) && $state->database) {
			if(!DB::getConn()) {
				// No connection, so try and connect to tmpdb if it exists
				if(isset($state->database)) {
					$this->oldDatabaseName = $databaseConfig['database'];
					$databaseConfig['database'] = $state->database;
				}

				// Connect to database
				DB::connect($databaseConfig);
			} else {
				// We've already connected to the database, do a fast check to see what database we're currently using
				$db = DB::query("SELECT DATABASE()")->value();
				if(isset($state->database) && $db != $state->database) {
					$this->oldDatabaseName = $databaseConfig['database'];
					$databaseConfig['database'] = $state->database;
					DB::connect($databaseConfig);
				}
			}
		}

		// Database
		if(!$this->isRunningTests()) {
			$dbName = (isset($state->database)) ? $state->database : null;

			if($dbName) {
				$dbExists = (bool)DB::query(
					sprintf("SHOW DATABASES LIKE '%s'", Convert::raw2sql($dbName))
				)->value();
			} else {
				$dbExists = false;
			}

			if(!$dbExists) {
				// Create a new one with a randomized name
				$dbName = SapphireTest::create_temp_db();

				$state->database = $dbName; // In case it's changed by the call to SapphireTest::create_temp_db();

				// Set existing one, assumes it already has been created
				$prefix = defined('SS_DATABASE_PREFIX') ? SS_DATABASE_PREFIX : 'ss_';
				$pattern = strtolower(sprintf('#^%stmpdb\d{7}#', $prefix));
				if(!preg_match($pattern, $dbName)) {
					throw new InvalidArgumentException("Invalid database name format");
				}

				$this->oldDatabaseName = $databaseConfig['database'];
				$databaseConfig['database'] = $dbName; // Instead of calling DB::set_alternative_db_name();

				// Connect to the new database, overwriting the old DB connection (if any)
				DB::connect($databaseConfig);
			}
		}

		// Mailer
		$mailer = (isset($state->mailer)) ? $state->mailer : null;

		if($mailer) {
			if(!class_exists($mailer) || !is_subclass_of($mailer, 'Mailer')) {
				throw new InvalidArgumentException(sprintf(
					'Class "%s" is not a valid class, or subclass of Mailer',
					$mailer
				));
			}
		}

		// Date and time
		if(isset($state->datetime)) {
			require_once 'Zend/Date.php';
			// Convert DatetimeField format
			if(!Zend_Date::isDate($state->datetime, 'yyyy-MM-dd HH:mm:ss')) {
				throw new LogicException(sprintf(
					'Invalid date format "%s", use yyyy-MM-dd HH:mm:ss',
					$state->datetime
				));
			}
		}

		$this->saveState($state);
		$this->extend('onAfterApplyState');
	}

	/**
	 * Import the database
	 *
	 * @param String $path Absolute path to a SQL dump (include DROP TABLE commands)
	 * @return void
	 */
	public function importDatabase($path, $requireDefaultRecords = false) {
		$sql = file_get_contents($path);

		// Split into individual query commands, removing comments
		$sqlCmds = array_filter(
			preg_split('/;\n/',
				preg_replace(array('/^$\n/m', '/^(\/|#).*$\n/m'), '', $sql)
			)
		);

		// Execute each query
		foreach($sqlCmds as $sqlCmd) {
			DB::query($sqlCmd);
		}

		// In case the dump involved CREATE TABLE commands, we need to ensure the schema is still up to date
		$dbAdmin = new DatabaseAdmin();
		Versioned::set_reading_mode('');
		$dbAdmin->doBuild(true, $requireDefaultRecords);
	}

	/**
	 * Build the database with default records, see {@link DataObject->requireDefaultRecords()}.
	 */
	public function requireDefaultRecords() {
		$dbAdmin = new DatabaseAdmin();
		Versioned::set_reading_mode('');
		$dbAdmin->doBuild(true, true);
	}

	/**
	 * Sliented as if the file already exists by another process, we don't want 
	 * to modify.
	 */
	public function saveState($state) {
		if (defined('JSON_PRETTY_PRINT')) {
			$content = json_encode($state, JSON_PRETTY_PRINT);
		} else {
			$content = json_encode($state);
		}
		file_put_contents($this->getFilePath(), $content, LOCK_EX);
	}

	public function loadFromFile() {
		if($this->isRunningTests()) {
			try {
				$contents = file_get_contents($this->getFilePath());
				$json = json_decode($contents);

				$this->applyState($json);
			} catch(Exception $e) {
				throw new \Exception("A test session appears to be in progress, but we can't retrieve the details. "
					. "Try removing the " . $this->getFilePath() . " file. Inner "
					. "error: " . $e->getMessage());
			}
		}
	}

	private function removeStateFile() {
		$file = $this->getFilePath();

		if(file_exists($file)) {
			if(!unlink($file)) {
				throw new \Exception('Unable to remove the testsession state file, please remove it manually. File '
					. 'path: ' . $file);
			}
		}
	}

	/**
	 * Cleans up the test session state by restoring the normal database connect (for the rest of this request, if any)
	 * and removes the {@link self::$test_state_file} so that future requests don't use this test state.
	 *
	 * Can be extended by implementing either onBeforeEndTestSession() or onAfterEndTestSession().
	 *
	 * This should implement itself cleanly in case it is called twice (e.g. don't throw errors when the state file
	 * doesn't exist anymore because it's already been cleaned up etc.) This is because during behat test runs where
	 * a queueing system (e.g. silverstripe-resque) is used, the behat module may call this first, and then the forked
	 * worker will call it as well - but there is only one state file that is created.
	 */
	public function endTestSession() {
		$this->extend('onBeforeEndTestSession');

		if(SapphireTest::using_temp_db()) {
			$this->resetDatabaseName();

			SapphireTest::set_is_running_test(false);
		}

		$this->removeStateFile();

		$this->extend('onAfterEndTestSession');
	}

	/**
	 * Loads a YAML fixture into the database as part of the {@link TestSessionController}.
	 *
	 * @param string $fixtureFile The .yml file to load
	 * @return FixtureFactory The loaded fixture
	 * @throws LogicException
	 */
	public function loadFixtureIntoDb($fixtureFile) {
		$realFile = realpath(BASE_PATH.'/'.$fixtureFile);
		$baseDir = realpath(Director::baseFolder());
		if(!$realFile || !file_exists($realFile)) {
			throw new LogicException("Fixture file doesn't exist");
		} else if(substr($realFile,0,strlen($baseDir)) != $baseDir) {
			throw new LogicException("Fixture file must be inside $baseDir");
		} else if(substr($realFile,-4) != '.yml') {
			throw new LogicException("Fixture file must be a .yml file");
		} else if(!preg_match('/^([^\/.][^\/]+)\/tests\//', $fixtureFile)) {
			throw new LogicException("Fixture file must be inside the tests subfolder of one of your modules.");
		}

		$factory = Injector::inst()->create('FixtureFactory');
		$fixture = Injector::inst()->create('YamlFixture', $fixtureFile);
		$fixture->writeInto($factory);

		$state = $this->getState();
		$state->fixtures[] = $fixtureFile;
		$this->applyState($state);

		return $fixture;
	}

	/**
	 * Reset the database connection to use the original database. Called by {@link self::endTestSession()}.
	 */
	public function resetDatabaseName() {
		if($this->oldDatabaseName) {
			global $databaseConfig;

			$databaseConfig['database'] = $this->oldDatabaseName;

			$conn = DB::getConn();

			if($conn) {
				$conn->selectDatabase($this->oldDatabaseName);
			}
		}
	}

	/**
	 * @return stdClass Data as taken from the JSON object in {@link self::loadFromFile()}
	 */
	public function getState() {
		$path = Director::getAbsFile($this->getFilePath());
		return (file_exists($path)) ? json_decode(file_get_contents($path)) : new stdClass;
	}
}