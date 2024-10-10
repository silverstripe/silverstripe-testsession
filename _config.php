<?php

use SilverStripe\ORM\DB;
use SilverStripe\TestSession\TestSessionEnvironment;

// Determine whether there is a testsession currently running, and if so - setup the persistent details for it.
TestSessionEnvironment::singleton()->loadFromFile();

/**
 * This closure will run every time a Resque_Event is forked (just before it is forked, so it applies to the parent
 * and child process).
 */
if (class_exists('Resque_Event') && class_exists('SSResqueRun')) {
    Resque_Event::listen('beforeFork', function ($data) {
        $databaseConfig = DB::getConfig(DB::CONN_PRIMARY);

        // Reconnect to the database - this may connect to the old DB first, but is required because these processes
        // are long-lived, and MySQL connections often get closed in between worker runs. We need to connect before
        // calling {@link TestSessionEnvironment::loadFromFile()}.
        DB::connect($databaseConfig);

        $testEnv = TestSessionEnvironment::singleton();

        if ($testEnv->isRunningTests()) {
            $testEnv->loadFromFile();
        } else {
            $testEnv->endTestSession();
        }
    });
}
