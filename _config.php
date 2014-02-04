<?php

// Determine whether there is a testsession currently running, and if so - setup the persistent details for it.
Injector::inst()->get('TestSessionEnvironment')->loadFromFile();