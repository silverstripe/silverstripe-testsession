<?php
/**
 * Allows inclusion of a PHP file, usually with procedural commands
 * to set up required test state. The file can be generated
 * through {@link TestSessionStubCodeWriter}, and the session state
 * set through {@link TestSessionController->set()} and the
 * 'testsessio.stubfile' state parameter.
 */
class TestSessionRequestFilter {
	
	public function preRequest($req, $session, $model) {
		$file = $session->inst_get('testsession.stubfile');
		if(!Director::isLive() && $file && file_exists($file)) {
			// Connect to the database so the included code can interact with it
			global $databaseConfig;
			if ($databaseConfig) DB::connect($databaseConfig);
			include_once($file);
		}
	}

	public function postRequest() {
	}
}