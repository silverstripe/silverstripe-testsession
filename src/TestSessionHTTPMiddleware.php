<?php

namespace SilverStripe\TestSession;

use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Email\Mailer;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Sets state previously initialized through {@link TestSessionController}.
 */
class TestSessionHTTPMiddleware implements HTTPMiddleware
{
    /**
     * @var TestSessionEnvironment
     */
    protected $testSessionEnvironment;

    public function __construct()
    {
        $this->testSessionEnvironment = TestSessionEnvironment::singleton();
    }

    public function process(HTTPRequest $request, callable $delegate)
    {
        // Init environment
        $this->testSessionEnvironment->init($request);

        // If not running tests, just pass through
        $isRunningTests = $this->testSessionEnvironment->isRunningTests();
        if (!$isRunningTests) {
            return $delegate($request);
        }

        // Load test state
        $this->loadTestState($request);
        TestSessionState::incrementState();

        // Call with safe teardown
        try {
            return $delegate($request);
        } finally {
            $this->restoreTestState($request);
            TestSessionState::decrementState();
        }
    }

    /**
     * Load test state from environment into "real" environment
     *
     * @param HTTPRequest $request
     */
    protected function loadTestState(HTTPRequest $request)
    {
        $state = $this->testSessionEnvironment->getState();
        $this->testSessionEnvironment->applyState($state);
    }

    /**
     * @param HTTPRequest $request
     * @return void
     */
    protected function restoreTestState(HTTPRequest $request)
    {
        // Store PHP session
        $state = $this->testSessionEnvironment->getState();
        $state->session = $request->getSession()->getAll();

        // skip saving file if the session is being closed (all test properties are removed except session)
        $keys = get_object_vars($state);
        if (count($keys) <= 1) {
            return;
        }

        $this->testSessionEnvironment->saveState($state);
    }
}
