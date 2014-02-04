<?php
/**
 * Sets state previously initialized through {@link TestSessionController}.
 */
class TestSessionRequestFilter {
	
	public function preRequest($req, $session, $model) {
		if(!Injector::inst()->get('TestSessionEnvironment')->isRunningTests()) return;

		$testState = Injector::inst()->get('TestSessionEnvironment')->getState();

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
	}
}