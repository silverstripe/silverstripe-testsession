<?php

namespace SilverStripe\TestSession;

use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Email\Mailer;
use SilverStripe\ORM\DataModel;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Session;
use SilverStripe\Control\Director;
use SilverStripe\Control\RequestFilter;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Sets state previously initialized through {@link TestSessionController}.
 */
class TestSessionRequestFilter implements RequestFilter
{
    /**
     * @var TestSessionEnvironment
     */
    protected $testSessionEnvironment;

    public function __construct()
    {
        $this->testSessionEnvironment = TestSessionEnvironment::singleton();
    }

    public function preRequest(HTTPRequest $request, Session $session, DataModel $model)
    {
        $isRunningTests = $this->testSessionEnvironment->isRunningTests();
        if (!$isRunningTests) {
            return;
        }

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
                global $databaseConfig;
                if ($databaseConfig) {
                    DB::connect($databaseConfig);
                }
                include_once($file);
            }
        }
    }

    public function postRequest(HTTPRequest $request, HTTPResponse $response, DataModel $model)
    {
        if (!$this->testSessionEnvironment->isRunningTests()) {
            return;
        }

        // Store PHP session
        $state = $this->testSessionEnvironment->getState();
        $state->session = Session::get_all();
        $this->testSessionEnvironment->applyState($state);
    }
}
