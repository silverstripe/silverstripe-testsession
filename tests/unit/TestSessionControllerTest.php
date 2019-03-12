<?php

namespace SilverStripe\TestSession\Tests\Unit;

use DateTime;
use LogicException;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Mailer;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\TestSession\TestSessionController;
use SilverStripe\TestSession\TestSessionEnvironment;
use SilverStripe\TestSession\TestSessionHTTPMiddleware;
use stdClass;
use SilverStripe\ORM\Connect\TempDatabase;
use SilverStripe\Core\Config\Config;

class TestSessionControllerTest extends SapphireTest
{
    /**
     * @var TestSessionEnvironment|PHPUnit_Framework_MockObject_MockObject
     */
    private $testSessionEnvironment;

    protected $usesDatabase = true;

    protected function setUp()
    {
        parent::setUp();
        Injector::inst()->unregisterNamedObject(TestSessionEnvironment::class);
        Injector::inst()->registerService(
            $this->createMock(TestSessionEnvironment::class),
            TestSessionEnvironment::class
        );
        $this->testSessionEnvironment = TestSessionEnvironment::singleton();
    }

    public function testIndex()
    {
        $controller = new TestSessionController();
        $this->assertContains('Start a new test session', (string) $controller->index());

        $env = $this->testSessionEnvironment;
        $env->method('isRunningTests')
            ->willReturn(true);

        $state = new stdClass();
        $state->showme = 'test';
        $env->method('getState')
            ->willReturn($state);

        $html = (string) $controller->index();
        $this->assertContains('Test session in progress.', $html);
        $this->assertContains('showme:', $html);
    }

    public function testStartNonGlobalImport()
    {
        DBDatetime::set_mock_now(1552525373);
        $params = [
            'datetime' => [
                'date' => DBDatetime::now()->Date(),
            ],
            'importDatabasePath' => '/somepath',
            'importDatabaseFilename' => 'bigOldb.sql',
            'requireDefaultRecords' => '1',
            'fixture' => 'fixture.yml',
        ];
        Director::mockRequest(function ($request) {
            $controller = new TestSessionController();
            $controller->setRequest($request);

            $env = $this->testSessionEnvironment;
            $state = new stdClass();

            $env->method('getState')
                ->willReturn($state);

            $env->expects($this->once())
                ->method('startTestSession')
                ->with(
                    [
                        'datetime' => 'Mar 14, 2019 00:00:00',
                        'importDatabasePath' => '/somepath',
                        'importDatabaseFilename' => 'bigOldb.sql',
                        'requireDefaultRecords' => '1',
                        'fixture' => 'fixture.yml',
                    ],
                    $this->isType('string')
                );

            // cannot do an import and default records
            $env->expects($this->never())
                ->method('requireDefaultRecords');

            $env->expects($this->once())
                ->method('loadFixtureIntoDb')
                ->with('fixture.yml');

            $html = (string) $controller->start();
            $this->assertContains('Test session in progress.', $html);

            $this->assertNotNull($request->getSession()->get('TestSessionId'));
        }, 'dev/testsession/start', $params);

        DBDatetime::clear_mock_now();
    }

    public function testStartNonGlobalDefaultRecords()
    {
        $params = [
            'requireDefaultRecords' => '1',
        ];
        Director::mockRequest(function ($request) {
            $controller = new TestSessionController();
            $controller->setRequest($request);

            $env = $this->testSessionEnvironment;
            $state = new stdClass();

            $env->method('getState')
                ->willReturn($state);

            $env->expects($this->once())
                ->method('startTestSession')
                ->with(
                    [
                        'requireDefaultRecords' => '1',
                    ],
                    $this->isType('string')
                );

            $env->expects($this->once())
                ->method('requireDefaultRecords')
                ->with();

            $controller->start();
        }, 'dev/testsession/start', $params);
    }

    public function testSetNotRunning()
    {
        $this->expectException(LogicException::class);
        $params = [];
        Director::mockRequest(function ($request) {
            $controller = new TestSessionController();
            $controller->setRequest($request);

            $controller->set();
        }, 'dev/testsession/set', $params);
    }

    public function testSetRunning()
    {
        DBDatetime::set_mock_now(1552525373);
        $params = [
            'datetime' => [
                'date' => DBDatetime::now()->Date(),
            ],
        ];

        Director::mockRequest(function ($request) {
            $controller = new TestSessionController();
            $controller->setRequest($request);

            $env = $this->testSessionEnvironment;
            $state = new stdClass();

            $env->method('isRunningTests')
                ->willReturn(true);

            $env->method('getState')
                ->willReturn($state);

            $env->expects($this->once())
                ->method('updateTestSession')
                ->with(
                    [
                        'datetime' => 'Mar 14, 2019 00:00:00',
                    ]
                );

            $html = (string) $controller->set();
            $this->assertContains('Test session in progress.', $html);
        }, 'dev/testsession/set', $params);

        DBDatetime::clear_mock_now();
    }

    public function testClearNotRunning()
    {
        $this->expectException(LogicException::class);
        $params = [];
        Director::mockRequest(function ($request) {
            $controller = new TestSessionController();
            $controller->setRequest($request);

            $controller->clear();
        }, 'dev/testsession/clear', $params);
    }

    public function testClearRunning()
    {

        $params = [];
        Director::mockRequest(function ($request) {
            $controller = new TestSessionController();
            $controller->setRequest($request);

            $env = $this->testSessionEnvironment;
            $state = new stdClass();

            $env->method('isRunningTests')
                ->willReturn(true);

            $env->method('getState')
                ->willReturn($state);

            Config::nest();
            Config::modify()->set(TempDatabase::class, 'factory', new class {
                public function create()
                {
                    $mockdb = $this->createMock(TempDatabase::class);
                    $mockdb->method('isUsed')->willReturn(true);
                    $mockdb->expects($this->once())
                        ->method('clearAllData');
                    return $mockdb;
                }
            });

            $msg = $controller->clear();
            Config::unnest();
            $this->assertEquals('Cleared database and test state', $msg);
        }, 'dev/testsession/clear', $params);
    }

    public function testEndNotRunning()
    {
        $this->expectException(LogicException::class);
        $params = [];
        Director::mockRequest(function ($request) {
            $controller = new TestSessionController();
            $controller->setRequest($request);

            $controller->end();
        }, 'dev/testsession/end', $params);
    }

    public function testEndRunning()
    {
        $params = [];
        Director::mockRequest(function ($request) {
            $controller = new TestSessionController();
            $controller->setRequest($request);

            $env = $this->testSessionEnvironment;
            $state = new stdClass();

            $env->method('isRunningTests')
                ->willReturn(true);

            $env->method('getState')
                ->willReturn($state);

            $env->expects($this->once())
                ->method('endTestSession');

            $html = (string) $controller->end();
            $this->assertContains('Test session ended', $html);

            $this->assertNull($request->getSession()->get('TestSessionId'));
        }, 'dev/testsession/end', $params);
    }
}
