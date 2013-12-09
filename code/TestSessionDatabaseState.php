<?php
/**
 * Used to share arbitrary state between the browser session
 * and other processes such as Behat CLI execution.
 * Assumes that the temporary database is reset automatically
 * on ending the test session.
 */
class TestSessionDatabaseState extends DataObject {
	
	private static $db = array(
		'Key' => 'Varchar(255)',
		'Value' => 'Text',
	);

	private static $indexes = array(
		'Key' => true
	);
}