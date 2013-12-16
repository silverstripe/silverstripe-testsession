<?php
/**
 * Stores the currently logged in user in the database in addition to 
 * PHP session. This means the information can be shared with other processes
 * such as a Behat CLI execution, without requiring this information to be available
 * through the UI (and potentially cause another page load via Selenium).
 */
class TestSessionMemberExtension extends DataExtension {

	public function memberLoggedIn() {
		if(!SapphireTest::using_temp_db()) return;

		$this->setCurrentMemberState();
	}

	public function onRegister() {
		if(!SapphireTest::using_temp_db()) return;

		$this->setCurrentMemberState();
	}

	public function memberLoggedOut() {
		if(!SapphireTest::using_temp_db()) return;

		$state = TestSessionDatabaseState::get()->filter('Key', 'CurrentMemberID')->removeAll();
	}

	protected function setCurrentMemberState() {
		$state = TestSessionDatabaseState::get()->find('Key', 'CurrentMemberID');
		if(!$state) {
			$state = new TestSessionDatabaseState(array(
				'Key' => 'CurrentMemberID'
			));
		}
		$state->Value = $this->owner->ID;
		$state->write();
	}

}