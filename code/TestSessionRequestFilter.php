<?php
/**
 * Sets state previously initialized through {@link TestSessionController}.
 */
class TestSessionRequestFilter {

	/**
	 * @var TestSessionEnvironment
	 */
	protected $testSessionEnvironment;

	public function __construct() {
		$this->testSessionEnvironment = Injector::inst()->get('testSessionEnvironment');
	}
	
	public function preRequest($req, $session, $model) {
		if(!$this->testSessionEnvironment->isRunningTests()) return;

		$testState = $this->testSessionEnvironment->getState();

		// Date and time
		if(isset($testState->datetime)) {
			SS_Datetime::set_mock_now($testState->datetime);
		}

		// Register mailer
		if(isset($testState->mailer)) {
			$mailer = $testState->mailer;
			Email::set_mailer(new $mailer());
			Config::inst()->update("Email","send_all_emails_to", null);
		}

		// Allows inclusion of a PHP file, usually with procedural commands
		// to set up required test state. The file can be generated
		// through {@link TestSessionStubCodeWriter}, and the session state
		// set through {@link TestSessionController->set()} and the
		// 'testsession.stubfile' state parameter.
		if(isset($testState->stubfile)) {
			$file = $testState->stubfile;
			if(!Director::isLive() && $file && file_exists($file)) {
				// Connect to the database so the included code can interact with it
				global $databaseConfig;
				if ($databaseConfig) DB::connect($databaseConfig);
				include_once($file);
			}
		}
	}

	public function postRequest() {
		if(!$this->testSessionEnvironment->isRunningTests()) return;
		
		// Store PHP session
		$state = $this->testSessionEnvironment->getState();
		$state->session = Session::get_all();
		$this->testSessionEnvironment->applyState($state);
	}
}