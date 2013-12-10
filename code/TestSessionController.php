<?php
/**
 * Requires PHP's mycrypt extension in order to set the database name as an encrypted cookie.
 */
class TestSessionController extends Controller {

	private static $allowed_actions = array(
		'index',
		'start',
		'set',
		'end',
		'clear',
	);

	private static $alternative_database_name = -1;

	public function init() {
		parent::init();
		
		$canAccess = (
			!Director::isLive()
			&& (Director::isDev() || Director::is_cli() || Permission::check("ADMIN"))
		);
		if(!$canAccess) return Security::permissionFailure($this);
	}

	public function Link($action = null) {
		return Controller::join_links(Director::baseUrl(), 'dev/testsession', $action);
	}
	
	/**
	 * Start a test session.
	 */
	public function start($request) {
		if(SapphireTest::using_temp_db()) return $this->renderWith('TestSession_inprogress');

		// Database
		$dbName = $request->getVar('database');
		if($dbName) {
			$dbExists = (bool)DB::query(
				sprintf("SHOW DATABASES LIKE '%s'", Convert::raw2sql($dbName))
			)->value();
		} else {
			$dbExists = false;
		}

		$this->extend('onBeforeStart', $dbName);

		if(!$dbExists) {
			// Create a new one with a randomized name
			$dbName = SapphireTest::create_temp_db();	
		}

		$this->setState(array_merge($request->getVars(), array('database' => $dbName)));

		$this->extend('onAfterStart', $dbName);
		
		return $this->renderWith('TestSession_start');
	}

	public function DatabaseName() {
		// Workaround for bug in Cookie::get(), fixed in 3.1-rc1
		if(self::$alternative_database_name != -1) {
			return self::$alternative_database_name;
		} else if ($dbname = DB::get_alternative_database_name()) {
			return $dbname;
		} else {
			$db = DB::getConn();
			if(method_exists($db, 'currentDatabase')) return $db->currentDatabase();
		}
	}

	public function set($request) {
		if(!SapphireTest::using_temp_db()) {
			throw new LogicException(
				"This command can only be used with a temporary database. "
				. "Perhaps you should use dev/testsession/start first?"
			);
		}

		$state = $request->getVars();
		$this->extend('onBeforeSet', $state);
		$this->setState($data);
		$this->extend('onAfterSet');

		return $this->renderWith('TestSession_inprogress');
	}

	public function clear($request) {
		if(!SapphireTest::using_temp_db()) {
			throw new LogicException(
				"This command can only be used with a temporary database. "
				. "Perhaps you should use dev/testsession/start first?"
			);
		}

		$this->extend('onBeforeClear');

		SapphireTest::empty_temp_db();
		
		if(isset($_SESSION['_testsession_codeblocks'])) {
			unset($_SESSION['_testsession_codeblocks']);
		}

		$this->extend('onAfterClear');

		return "Cleared database and test state";
	}
	
	public function end() {
		if(!SapphireTest::using_temp_db()) {
			throw new LogicException(
				"This command can only be used with a temporary database. "
				. "Perhaps you should use dev/testsession/start first?"
			);
		}

		$this->extend('onBeforeEnd');

		SapphireTest::kill_temp_db();
		DB::set_alternative_database_name(null);
		// Workaround for bug in Cookie::get(), fixed in 3.1-rc1
		self::$alternative_database_name = null;
		Session::clear('testsession');

		$this->extend('onAfterEnd');

		return $this->renderWith('TestSession_end');
	}

	protected function loadFixtureIntoDb($fixtureFile) {
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

		Session::add_to_array('testsession.fixtures', $fixtureFile);

		return $fixture;
	}

	/**
	 * @return boolean
	 */
	public function isTesting() {
		return SapphireTest::using_temp_db();
	}

	public function setState($data) {
		// Database
		$dbname = (isset($data['database'])) ? $data['database'] : null;
		if($dbname) {
			// Set existing one, assumes it already has been created
			$prefix = defined('SS_DATABASE_PREFIX') ? SS_DATABASE_PREFIX : 'ss_';
			$pattern = strtolower(sprintf('#^%stmpdb\d{7}#', $prefix));
			if(!preg_match($pattern, $dbname)) {
				throw new InvalidArgumentException("Invalid database name format");
			}
			DB::set_alternative_database_name($dbname);
			// Workaround for bug in Cookie::get(), fixed in 3.1-rc1
			self::$alternative_database_name = $dbname;

			// Database name is set in cookie (next request), ensure its available on this request already
			global $databaseConfig;
			DB::connect(array_merge($databaseConfig, array('database' => $dbname)));
			unset($data['database']);
		}

		// Fixtures
		$fixtureFile = (isset($data['fixture'])) ? $data['fixture'] : null;
		if($fixtureFile) {
			$this->loadFixtureIntoDb($fixtureFile);
			unset($data['fixture']);
		} 

		// Mailer
		$mailer = (isset($data['mailer'])) ? $data['mailer'] : null;
		if($mailer) {
			if(!class_exists($mailer) || !is_subclass_of($mailer, 'Mailer')) {
				throw new InvalidArgumentException(sprintf(
					'Class "%s" is not a valid class, or subclass of Mailer',
					$mailer
				));
			}

			// Configured through testsession/_config.php
			Session::set('testsession.mailer', $mailer);	
			unset($data['mailer']);
		}

		// Date
		$date = (isset($data['date'])) ? $data['date'] : null;
		if($date) {
			require_once 'Zend/Date.php';
			if(!Zend_Date::isDate($date, 'yyyy-MM-dd HH:mm:ss')) {
				throw new LogicException(sprintf(
					'Invalid date format "%s", use yyyy-MM-dd HH:mm:ss',
					$date
				));
			}

			// Configured through testsession/_config.php
			Session::set('testsession.date', $date);
			unset($data['date']);
		}

		// Set all other keys without special handling
		if($data) foreach($data as $k => $v) {
			Session::set('testsession.' . $k, $v);
		}
	}

	/**
	 * @return ArrayList
	 */
	public function getState() {
		$state = array();
		if($dbname = DB::get_alternative_database_name()) {
			$state[] = new ArrayData(array(
				'Name' => 'Database',
				'Value' => $dbname,
			));
		}
		$sessionStates = Session::get('testsession');
		if($sessionStates) foreach($sessionStates as $k => $v) {
			$state[] = new ArrayData(array(
				'Name' => $k,
				'Value' => var_export($v, true)
			));
		}

		return new ArrayList($state);
	}

}