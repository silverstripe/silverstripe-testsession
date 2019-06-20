<?php

namespace SilverStripe\TestSession;

use FilesystemIterator;
use LogicException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\Connect\TempDatabase;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Permission;
use SilverStripe\Security\RandomGenerator;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;

/**
 * Requires PHP's mycrypt extension in order to set the database name as an encrypted cookie.
 */
class TestSessionController extends Controller
{
    private static $url_segment = 'dev/testsession';

    private static $allowed_actions = array(
        'index',
        'start',
        'set',
        'end',
        'clear',
        'browsersessionstate',
        'StartForm',
        'ProgressForm',
    );

    private static $alternative_database_name = -1;

    /**
     * @var String Absolute path to a folder containing *.sql dumps.
     */
    private static $database_templates_path;

    /**
     * @var TestSessionEnvironment
     */
    protected $environment;

    public function __construct()
    {
        parent::__construct();

        $this->environment = TestSessionEnvironment::singleton();
    }

    public function init()
    {
        parent::init();

        $this->extend('init');

        $canAccess = (
            !Director::isLive()
            && (Director::isDev() || Director::isTest() || Director::is_cli() || Permission::check("ADMIN"))
        );
        if (!$canAccess) {
            Security::permissionFailure($this);
            return;
        }

        Requirements::javascript('//code.jquery.com/jquery-1.7.2.min.js');
        Requirements::javascript('silverstripe/testsession:client/js/testsession.js');
    }

    public function index()
    {
        if ($this->environment->isRunningTests()) {
            return $this->renderWith('TestSession_inprogress');
        } else {
            return $this->renderWith('TestSession_start');
        }
    }

    /**
     * Start a test session. If you wish to extend how the test session is started (and add additional test state),
     * then take a look at {@link TestSessionEnvironment::startTestSession()} and
     * {@link TestSessionEnvironment::applyState()} to see the extension points.
     */
    public function start()
    {
        $params = $this->getRequest()->requestVars();

        if (!empty($params['globalTestSession'])) {
            $id = null;
        } else {
            $generator = Injector::inst()->get(RandomGenerator::class);
            $id = substr($generator->randomToken(), 0, 10);
            $this->getRequest()->getSession()->set('TestSessionId', $id);
        }

        // Convert datetime from form object into a single string
        $params = $this->fixDatetimeFormField($params);

        // Remove unnecessary items of form-specific data from being saved in the test session
        $params = array_diff_key(
            $params,
            array(
                'action_set' => true,
                'action_start' => true,
                'SecurityID' => true,
                'url' => true,
                'flush' => true,
            )
        );

        $this->environment->startTestSession($params, $id);

        // Optionally import database
        if (!empty($params['importDatabasePath']) || !empty($params['importDatabaseFilename'])) {
            $absPath = '';

            // by path
            if (!empty($params['importDatabasePath'])) {
                $absPath = $params['importDatabasePath'];

            // by filename
            } elseif (!empty($params['importDatabaseFilename'])) {
                foreach ($this->getDatabaseTemplates() as $tAbsPath => $tFilename) {
                    if ($tFilename === $params['importDatabaseFilename']) {
                        $absPath = $tAbsPath;
                        break;
                    }
                }
            }
            $this->environment->importDatabase(
                $absPath,
                !empty($params['requireDefaultRecords']) ? $params['requireDefaultRecords'] : false
            );
        } elseif (!empty($params['requireDefaultRecords']) && $params['requireDefaultRecords']) {
            $this->environment->requireDefaultRecords();
        }

        // Fixtures
        $fixtureFile = (!empty($params['fixture'])) ? $params['fixture'] : null;
        if ($fixtureFile) {
            $this->environment->loadFixtureIntoDb($fixtureFile);
        }

        return $this->renderWith('TestSession_inprogress');
    }

    /**
     * Set $_SESSION state for the current browser session.
     *
     * @param HTTPRequest $request
     */
    public function browsersessionstate($request)
    {
        if (!$this->environment->isRunningTests()) {
            throw new LogicException("No test session in progress.");
        }

        $newSessionStates = array_diff_key($request->getVars(), array('url' => true));
        if (!$newSessionStates) {
            throw new LogicException('No query parameters detected');
        }

        $session = $this->getRequest()->getSession();
        $sessionStates = (array)$session->get('_TestSessionController.BrowserSessionState');

        foreach ($newSessionStates as $k => $v) {
            $session->set($k, $v);
        }

        // Track which state we're setting so we can unset later in end()
        $session->set('_TestSessionController.BrowserSessionState', array_merge($sessionStates, $newSessionStates));
    }

    public function StartForm()
    {
        $databaseTemplates = $this->getDatabaseTemplates();
        $fields = new FieldList(
            new CheckboxField('createDatabase', 'Create temporary database?', 1)
        );
        if ($databaseTemplates) {
            $fields->push(
                $dropdown = new DropdownField('importDatabasePath', false)
            );

            $dropdown->setSource($databaseTemplates)
                ->setEmptyString('Empty database');
        }
        $fields->push(new CheckboxField('requireDefaultRecords', 'Create default data?'));
        if (Director::isDev()) {
            $fields->push(
                CheckboxField::create('globalTestSession', 'Use global test session?')
                    ->setDescription('Caution: Will apply to all users across browsers')
            );
        }
        $fields->merge($this->getBaseFields());
        $form = new Form(
            $this,
            'StartForm',
            $fields,
            new FieldList(
                new FormAction('start', 'Start Session')
            )
        );

        $this->extend('updateStartForm', $form);

        return $form;
    }

    /**
     * Shows state which is allowed to be modified while a test session is in progress.
     */
    public function ProgressForm()
    {
        $fields = $this->getBaseFields();
        $form = new Form(
            $this,
            'ProgressForm',
            $fields,
            new FieldList(
                new FormAction('set', 'Set testing state')
            )
        );


        $form->setFormAction($this->Link('set'));

        $this->extend('updateProgressForm', $form);

        return $form;
    }

    protected function getBaseFields()
    {
        $testState = $this->environment->getState();

        $fields = new FieldList(
            $textfield = new TextField('fixture', 'Fixture YAML file path'),
            $datetimeField = new DatetimeField('datetime', 'Custom date'),
            new HiddenField('flush', null, 1)
        );
        $textfield->setAttribute('placeholder', 'Example: framework/tests/security/MemberTest.yml');
        $datetimeField->setValue((isset($testState->datetime) ? $testState->datetime : null));

        $this->extend('updateBaseFields', $fields);

        return $fields;
    }

    public function DatabaseName()
    {
        $db = DB::get_conn();
        return $db->getSelectedDatabase();
    }

    /**
     * Updates an in-progress {@link TestSessionEnvironment} object with new details. This could be loading in new
     * fixtures, setting the mocked date to another value etc.
     *
     * @return DBHTMLText Rendered Template
     * @throws LogicException
     */
    public function set()
    {
        if (!$this->environment->isRunningTests()) {
            throw new LogicException("No test session in progress.");
        }

        $params = $this->request->requestVars();

        // Convert datetime from form object into a single string
        $params = $this->fixDatetimeFormField($params);

        // Remove unnecessary items of form-specific data from being saved in the test session
        $params = array_diff_key(
            $params,
            array(
                'action_set' => true,
                'action_start' => true,
                'SecurityID' => true,
                'url' => true,
                'flush' => true,
            )
        );

        $this->environment->updateTestSession($params);

        return $this->renderWith('TestSession_inprogress');
    }

    public function clear()
    {
        if (!$this->environment->isRunningTests()) {
            throw new LogicException("No test session in progress.");
        }

        $this->extend('onBeforeClear');

        $tempDB = TempDatabase::create();
        if ($tempDB->isUsed()) {
            $tempDB->clearAllData();
        }

        if (isset($_SESSION['_testsession_codeblocks'])) {
            unset($_SESSION['_testsession_codeblocks']);
        }

        $this->extend('onAfterClear');

        return "Cleared database and test state";
    }

    /**
     * As with {@link self::start()}, if you want to extend the functionality of this, then look at
     * {@link TestSessionEnvironent::endTestSession()} as the extension points have moved to there now that the logic
     * is there.
     */
    public function end()
    {
        if (!$this->environment->isRunningTests()) {
            throw new LogicException("No test session in progress.");
        }

        $this->environment->endTestSession();
        $session = Controller::curr()->getRequest()->getSession();
        $session->clear('TestSessionId');

        // Clear out all PHP session states which have been set previously
        if ($sessionStates = $session->get('_TestSessionController.BrowserSessionState')) {
            foreach ($sessionStates as $k => $v) {
                $session->clear($k);
            }
            $session->clear('_TestSessionController');
        }

        return $this->renderWith('TestSession_end');
    }

    /**
     * @return boolean
     */
    public function isTesting()
    {
        $tempDB = new TempDatabase();
        return $tempDB->isUsed();
    }

    /**
     * @return ArrayList
     */
    public function getState()
    {
        $stateObj = $this->environment->getState();
        $state = array();

        // Convert the stdObject of state into ArrayData
        foreach ($stateObj as $k => $v) {
            $state[] = new ArrayData(array(
                'Name' => $k,
                'Value' => var_export($v, true)
            ));
        }

        return new ArrayList($state);
    }

    /**
     * Get all *.sql database files located in a specific path,
     * keyed by their file name.
     *
     * @param  String $path Absolute folder path
     * @return array
     */
    protected function getDatabaseTemplates($path = null)
    {
        $templates = array();

        if (!$path) {
            $path = $this->config()->get('database_templates_path');
        }

        // TODO Remove once we can set BASE_PATH through the config layer
        if ($path && !Director::is_absolute($path)) {
            $path = BASE_PATH . '/' . $path;
        }

        if ($path && file_exists($path)) {
            $it = new FilesystemIterator($path);
            foreach ($it as $fileinfo) {
                if ($fileinfo->getExtension() != 'sql') {
                    continue;
                }
                $templates[$fileinfo->getRealPath()] = $fileinfo->getFilename();
            }
        }

        return $templates;
    }

    /**
     * @param $params array The form fields as passed through from ->start() or ->set()
     * @return array The form fields, after fixing the datetime field if necessary
     */
    private function fixDatetimeFormField($params)
    {
        if (isset($params['datetime']) && is_array($params['datetime']) && !empty($params['datetime']['date'])) {
            // Convert DatetimeField format from array into string
            $datetime = $params['datetime']['date'];
            $datetime .= ' ';
            $datetime .= (@$params['datetime']['time']) ? $params['datetime']['time'] : '00:00:00';
            $params['datetime'] = $datetime;
        } elseif (isset($params['datetime']) && empty($params['datetime']['date'])) {
            unset($params['datetime']); // No datetime, so remove the param entirely
        }

        return $params;
    }
}
