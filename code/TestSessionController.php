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
		'StartForm',
		'ProgressForm',
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

	public function index() {
		if(Session::get('testsession.started')) {
			return $this->renderWith('TestSession_inprogress');
		} else {
			return $this->renderWith('TestSession_start');
		}
	}
	
	/**
	 * Start a test session.
	 */
	public function start() {
		$this->extend('onBeforeStart');
		$params = $this->request->requestVars();
		if(isset($params['createDatabase'])) $params['createDatabase'] = 1; // legacy default behaviour
		$this->setState($params);
		$this->extend('onAfterStart');
		
		return $this->renderWith('TestSession_inprogress');
	}

	public function StartForm() {
		$fields = new FieldList(
			new CheckboxField('createDatabase', 'Create temporary database?', 1)
		);
		$fields->merge($this->getBaseFields());
		$form = new Form(
			$this, 
			'StartForm',
			$fields,
			new FieldList(
				new FormAction('start', 'Start Session')
			)
		);
		
		$this->extend('updateStartForm', $form);

		return $form;
	}

	/**
	 * Shows state which is allowed to be modified while a test session is in progress.
	 */
	public function ProgressForm() {
		$fields = $this->getBaseFields();
		$form = new Form(
			$this, 
			'ProgressForm',
			$fields,
			new FieldList(
				new FormAction('set', 'Set testing state')
			)
		);
		
		
		$form->setFormAction($this->Link('set'));

		$this->extend('updateProgressForm', $form);

		return $form;
	}

	protected function getBaseFields() {
		$fields = new FieldList(
			(new TextField('fixture', 'Fixture YAML file path'))
				->setAttribute('placeholder', 'Example: framework/tests/security/MemberTest.yml'),
			$datetimeField = new DatetimeField('datetime', 'Custom date'),
			new HiddenField('flush', null, 1)
		);
		$datetimeField->getDateField()
			->setConfig('dateformat', 'yyyy-MM-dd')
			->setConfig('showcalendar', true)
			->setAttribute('placeholder', 'Date (yyyy-MM-dd)');
		$datetimeField->getTimeField()
			->setConfig('timeformat', 'HH:mm:ss')
			->setAttribute('placeholder', 'Time (HH:mm:ss)');
		$datetimeField->setValue(Session::get('testsession.datetime'));

		$this->extend('updateBaseFields', $fields);

		return $fields;
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

	public function set() {
		if(!Session::get('testsession.started')) {
			throw new LogicException("No test session in progress.");
		}

		$params = $this->request->requestVars();
		$this->extend('onBeforeSet', $params);
		$this->setState($params);
		$this->extend('onAfterSet');

		return $this->renderWith('TestSession_inprogress');
	}

	public function clear() {
		if(!Session::get('testsession.started')) {
			throw new LogicException("No test session in progress.");
		}

		$this->extend('onBeforeClear');

		if(SapphireTest::using_temp_db()) {
			SapphireTest::empty_temp_db();
		}
		
		if(isset($_SESSION['_testsession_codeblocks'])) {
			unset($_SESSION['_testsession_codeblocks']);
		}

		$this->extend('onAfterClear');

		return "Cleared database and test state";
	}
	
	public function end() {
		if(!Session::get('testsession.started')) {
			throw new LogicException("No test session in progress.");
		}

		$this->extend('onBeforeEnd');

		if(SapphireTest::using_temp_db()) {
			SapphireTest::kill_temp_db();
			DB::set_alternative_database_name(null);
			// Workaround for bug in Cookie::get(), fixed in 3.1-rc1
			self::$alternative_database_name = null;
		}

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
		// Filter keys
		$data = array_diff_key(
			$data,
			array(
				'action_set' => true, 
				'action_start' => true,
				'SecurityID' => true, 
				'url' => true, 
				'flush' => true,
			)
		);

		// Database
		if(
			!Session::get('testsession.started') 
			&& (@$data['createDatabase'] || @$data['database'])
		) {
			$dbName = (isset($data['database'])) ? $data['database'] : null;
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
			}

			// Set existing one, assumes it already has been created
			$prefix = defined('SS_DATABASE_PREFIX') ? SS_DATABASE_PREFIX : 'ss_';
			$pattern = strtolower(sprintf('#^%stmpdb\d{7}#', $prefix));
			if(!preg_match($pattern, $dbName)) {
				throw new InvalidArgumentException("Invalid database name format");
			}
			DB::set_alternative_database_name($dbName);
			// Workaround for bug in Cookie::get(), fixed in 3.1-rc1
			self::$alternative_database_name = $dbName;

			// Database name is set in cookie (next request), ensure its available on this request already
			global $databaseConfig;
			DB::connect(array_merge($databaseConfig, array('database' => $dbName)));
			if(isset($data['database'])) unset($data['database']);
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
			Session::set('testsession.mailer', $mailer);	
			unset($data['mailer']);
		}

		// Date and time
		if(@$data['datetime']['date'] && @$data['datetime']['time']) {
			require_once 'Zend/Date.php';
			// Convert DatetimeField format
			$datetime = $data['datetime']['date'] . ' ' . $data['datetime']['time'];
			if(!Zend_Date::isDate($datetime, 'yyyy-MM-dd HH:mm:ss')) {
				throw new LogicException(sprintf(
					'Invalid date format "%s", use yyyy-MM-dd HH:mm:ss',
					$datetime
				));
			}
			Session::set('testsession.datetime', $datetime);
			unset($data['datetime']);
		} else {
			unset($data['datetime']);
		}

		// Set all other keys without special handling
		if($data) foreach($data as $k => $v) {
			Session::set('testsession.' . $k, $v);
		}

		Session::set('testsession.started', true);
	}

	/**
	 * @return ArrayList
	 */
	public function getState() {
		$state = array();
		$state[] = new ArrayData(array(
				'Name' => 'Database',
				'Value' => DB::getConn()->currentDatabase(),
			));
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