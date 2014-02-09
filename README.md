# Browser Test Session Module

## Overview

This module starts a testing session in a browser,
in order to test a SilverStripe application in a clean state.
Usually the session is started on a fresh database with only default records loaded.
Further data can be loaded from YAML fixtures or database dumps.

The session is persisted in a file which is generated upon starting the session.
As long as this file exists, the test session is considered in progress,
both in web browsers and command-line execution. By default, the file
is stored in the webroot under `TESTS_RUNNING.js`.

The module also serves as an initializer for the
[SilverStripe Behat Extension](https://github.com/silverstripe-labs/silverstripe-behat-extension/).
It is required for Behat because the Behat CLI test runner needs to persist
test configuration just for the tested browser connection,
available on arbitary URL endpoints. For example,
we're setting up a test mailer which writes every email
into a temporary database table for inspection by the CLI-based process.

## Setup

Simply require the module in a SilverStripe webroot (3.0 or newer):

  composer require silverstripe/behat-extension

## Usage

You start a test session by manually requesting a certain URL,
and interact with it through other URL endpoints.

Commands:

 * `dev/testsession`: Shows options for starting a test session
 * `dev/testsession/start`: Sets up test state, most commonly a test database will be constructed, 
    and your browser session will be amended to use this database. See "Parameters" documentation below.
 * `dev/testsession/end`: Removes the test state, and resets to the original database.
 * `dev/testsession/loadfixture?fixture=<path>`: Loads a fixture into an existing test state.
 * `dev/testsession/clear`: Empties the test state.

While you can use the interface to set the test session state,
it can be useful to set them programmatically through query parameters
on "dev/testsession/start":

 * `fixture`: Loads a YAML fixture in the format generally accepted by `SapphireTest` 
   (see [fixture format docs](http://doc.silverstripe.org/framework/en/topics/testing/fixtures)). 
   The path should be relative to the webroot.
 * `createDatabase`: Create a temporary database.
 * `createDatabaseTemplate`: Path to a database dump to load into a newly created temporary database.
 * `database`: Set an alternative database name in the current 
    browser session as a cookie. Does not actually create the database, 
    that's usually handled by `SapphireTest::create_temp_db()`.
    Note: The database names are limited to a specific naming convention as a security measure:
    The "ss_tmpdb" prefix and a random sequence of seven digits.
    This avoids the user gaining access to other production databases available on the same connection.
 * `mailer`: Subclass of `Mailer`, typically used to record emails instead of actually sending them.
 * `datetime`: Sets a simulated date used for all framework operations.
    Format as "yyyy-MM-dd HH:mm:ss" (Example: "2012-12-31 18:40:59").

Example usage with parameters:

	dev/testsession/start?database=ss_tmpdb_1234567&fixture=cms/tests/controller/CMSMainTest.yml
