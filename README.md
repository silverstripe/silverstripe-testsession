# Browser Test Session Module

## Overview

This module starts a testing session in a browser,
in order to test a SilverStripe application in a clean state.
Usually the session is started on a fresh database with only default records loaded.
Further data can be loaded from YAML fixtures.

The module also serves as an initializer for the
[SilverStripe Behat Extension](https://github.com/silverstripe-labs/silverstripe-behat-extension/).
It is required for Behat because the Behat CLI test runner needs to persist
test configuration just for the tested browser connection,
available on arbitary URL endpoints. For example,
we're setting up a test mailer which writes every email
into a temporary database table for inspection by the CLI-based process.

## Setup

In order to execute the commands, the environment must be in "dev mode",
or you must be logged-in with administrative permissions.

Since the database name is stored as an encrypted cookie,
you need to create a secure token for the encryption first:

	sake dev/generatesecuretoken

The resulting configuration code needs to be placed in `mysite/_config.php`.

## Usage

 * `dev/testsession/start`: A test database will be constructed, and your
	  browser session will be amended to use this database.
 * `dev/testsession/start?fixture=<fixturefile>`: Same as above, but also loads a YAML fixture
   in the format generally accepted by `SapphireTest` (see [fixture format docs](http://doc.silverstripe.org/framework/en/topics/testing/fixtures)). The path should be relative to the webroot.
 * `dev/testsession/end`: Removes the test state, and resets to the original database
 * `dev/testsession/setdb?database=<database-name>`: Set an alternative database name in the current 
    browser session as a cookie. Does not actually create the database, 
    that's usually handley by `SapphireTest::create_temp_db()`.
 * `dev/testsession/emptydb`: Empties the test state.

Note: The database names are limited to a specific naming convention as a security measure:
The "ss_tmpdb" prefix and a random sequence of seven digits.
This avoids the user gaining access to other production databases available on the same connection.
