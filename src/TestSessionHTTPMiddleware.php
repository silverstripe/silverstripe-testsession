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
        $testState = $this->testSessionEnvironment->getState();

        // Date and time
        if (isset($testState->datetime)) {
            DBDatetime::set_mock_now($testState->datetime);
        }

        // Register mailer
        if (isset($testState->mailer)) {
            $mailer = $testState->mailer;
            Injector::inst()->registerService(new $mailer(), Mailer::class);
            Email::config()->set("send_all_emails_to", null);
        }

        // Allows inclusion of a PHP file, usually with procedural commands
        // to set up required test state. The file can be generated
        // through {@link TestSessionStubCodeWriter}, and the session state
        // set through {@link TestSessionController->set()} and the
        // 'testsession.stubfile' state parameter.
        if (isset($testState->stubfile)) {
            $file = $testState->stubfile;
            if (!Director::isLive() && $file && file_exists($file)) {
                // Connect to the database so the included code can interact with it
                $databaseConfig = DB::getConfig();
                if ($databaseConfig) {
                    DB::connect($databaseConfig);
                }
                include_once($file);
            }
        }
    }

    protected function restoreTestState(HTTPRequest $request)
    {
        // Store PHP session
        $state = $this->testSessionEnvironment->getState();
        $state->session = $request->getSession()->getAll();
        $this->testSessionEnvironment->applyState($state);
    }
}
