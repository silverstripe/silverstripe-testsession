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
		if($request->getVar('database')) {
			$dbExists = (bool)DB::query(
				sprintf("SHOW DATABASES LIKE '%s'", Convert::raw2sql($request->getVar('database')))
			)->value();
		} else {
			$dbExists = false;
		}

		if(!$dbExists) {
			// Create a new one with a randomized name
			$dbname = SapphireTest::create_temp_db();	
			DB::set_alternative_database_name($dbname);
			// Workaround for bug in Cookie::get(), fixed in 3.1-rc1
			self::$alternative_database_name = $dbname;
		}

		$this->setState($request->getVars());
		
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

		$this->setState($request->getVars());

		return $this->renderWith('TestSession_inprogress');
	}

	public function clear($request) {
		if(!SapphireTest::using_temp_db()) {
			throw new LogicException(
				"This command can only be used with a temporary database. "
				. "Perhaps you should use dev/testsession/start first?"
			);
		}

		SapphireTest::empty_temp_db();

		return "Cleared database and test state";
	}
	
	public function end() {
		if(!SapphireTest::using_temp_db()) {
			throw new LogicException(
				"This command can only be used with a temporary database. "
				. "Perhaps you should use dev/testsession/start first?"
			);
		}

		SapphireTest::kill_temp_db();
		DB::set_alternative_database_name(null);
		// Workaround for bug in Cookie::get(), fixed in 3.1-rc1
		self::$alternative_database_name = null;
		Session::clear('testsession');

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
		}

		// Fixtures
		$fixtureFile = (isset($data['fixture'])) ? $data['fixture'] : null;
		if($fixtureFile) {
			$this->loadFixtureIntoDb($fixtureFile);
		} else {
			// If no fixture, then use defaults
			$dataClasses = ClassInfo::subclassesFor('DataObject');
			array_shift($dataClasses);
			foreach($dataClasses as $dataClass) singleton($dataClass)->requireDefaultRecords();
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
		if($fixtures = Session::get('testsession.fixtures')) {
			$state[] = new ArrayData(array(
				'Name' => 'Fixture',
				'Value' => implode(',', array_unique($fixtures)),
			));	
		}
		if($mailer = Session::get('testsession.mailer')) {
			$state[] = new ArrayData(array(
				'Name' => 'Mailer Class',
				'Value' => $mailer,
			));	
		}
		if($date = Session::get('testsession.date')) {
			$state[] = new ArrayData(array(
				'Name' => 'Date',
				'Value' => $date,
			));	
		}

		return new ArrayList($state);
	}

}