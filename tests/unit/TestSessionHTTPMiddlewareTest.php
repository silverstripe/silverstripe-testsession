<?php

namespace SilverStripe\TestSession\Tests\Unit;

use DateTime;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\TestSession\TestSessionEnvironment;
use SilverStripe\TestSession\TestSessionHTTPMiddleware;
use stdClass;
use SilverStripe\Control\Email\Mailer;
use SilverStripe\Core\Manifest\ModuleLoader;

class TestSessionHTTPMiddlewareTest extends SapphireTest
{
    /**
     * @var TestSessionEnvironment
     */
    private $testSessionEnvironment;

    protected $usesDatabase = true;

    protected function setUp() : void
    {
        parent::setUp();
        Injector::inst()->registerService(
            $this->createMock(TestSessionEnvironment::class),
            TestSessionEnvironment::class
        );
        $this->testSessionEnvironment = TestSessionEnvironment::singleton();
    }

    public function testProcessNoTestRunning()
    {
        $env = $this->testSessionEnvironment;

        // setup expected calls on environment
        $env->expects($this->never())
            ->method('getState');

        $env->expects($this->never())
            ->method('applyState');

        Injector::nest();

        Injector::inst()->registerService(
            $env,
            TestSessionEnvironment::class
        );

        $middleware = new TestSessionHTTPMiddleware();
        // Mock request
        $session = new Session([]);
        $request = new HTTPRequest('GET', '/');
        $request->setSession($session);
        $delegate = function () {
            // noop
        };
        $middleware->process($request, $delegate);

        Injector::unnest();
    }

    public function testProcessTestsRunning()
    {

        $state = new stdClass();
        $env = $this->testSessionEnvironment;

        $env->method('isRunningTests')
            ->willReturn(true);

        // setup expected calls on environment
        $env->expects($this->exactly(2))
            ->method('getState')
            ->willReturn($state);

        $env->expects($this->once())
            ->method('applyState');

        Injector::nest();

        Injector::inst()->registerService(
            $env,
            TestSessionEnvironment::class
        );

        $middleware = new TestSessionHTTPMiddleware();
        // Mock request
        $session = new Session([]);
        $request = new HTTPRequest('GET', '/');
        $request->setSession($session);
        $delegate = function () {
            // noop
        };
        $middleware->process($request, $delegate);

        Injector::unnest();
    }
}
