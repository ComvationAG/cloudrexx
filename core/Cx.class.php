<?php

/**
 * Main script for Contrexx
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Michael Ritter <michael.ritter@comvation.com>
 * @package     contrexx
 * @subpackage  core
 * @link        http://www.contrexx.com/ contrexx homepage
 * @since       v3.1.0
 */

namespace {
    /**
     * Wrapper for new \Cx\Core\Cx()
     * 
     * This is necessary, because we cannot use namespaces in index.php
     * in order to catch errors with PHP versions prior to 5.3
     * @param string $mode (optional) One of 'frontend', 'backend', 'cli', 'minimal'
     */
    function init($mode = null) {
        new \Cx\Core\Cx($mode);
    }
}

namespace Cx\Core {

    /**
     * This loads and controls everything
     * @todo Remove all instances of "global" or at least move them to a single place
     */
    class Cx {
        /**
         * Commandline interface mode
         * 
         * In this mode, Contrexx is initialized for commandline usage
         * This mode is BETA at this time
         */
        const MODE_CLI = 'cli';
        
        /**
         * Frontend mode
         * 
         * In this mode, Contrexx shows the frontend
         */
        const MODE_FRONTEND = 'frontend';
        
        /**
         * Backend mode
         * 
         * In this mode, Contrexx show the administrative backend
         */
        const MODE_BACKEND = 'backend';
        
        /**
         * Minimal mode
         * 
         * In this mode, the whole environment is loaded, but the
         * main template will not be initialized, no component hooks
         * will be executed and the template will not be parsed
         * This mode is BETA at this time
         */
        const MODE_MINIMAL = 'minimal';
        
        /**
         * Parsing star time
         * @var array Array in the form array({milliseconds}, {seconds})
         */
        protected $startTime = array();
        
        /**
         * System mode
         * @var string Mode as string (see constants)
         */
        protected $mode = null;

        /**
         * Main template
         * @var \Cx\Core\Html\Sigma
         */
        protected $template = null;

        /**
         * Database connection handler
         * @var \Cx\Core\Db\Db
         */
        protected $db = null;

        /**
         * Request URL
         * @var \Cx\Core\Routing\Url
         */
        protected $request = null;
        
        /**
         * Component handler
         * @var \Cx\Core\Component\Controller\ComponentHandler
         */
        protected $ch = null;
        
        /**
         * Class auto loader
         * @var \Cx\Core\ClassLoader\ClassLoader
         */
        protected $cl = null;
        
        /**
         * If null, customizing is deactivated
         * @var string
         */
        protected $customizingPath = null;
        
        /**
         * If null, page is not resolved yet
         * @var \Cx\Core\ContentManager\Model\Entity\Page
         */
        protected $resolvedPage = null;
        
        /**
         * Resolver used for page resolving (for the moment frontend mode only)
         * @var \Cx\Core\Routing\Resolver 
         */
        protected $resolver = null;
        
        /**
         * Current language id
         * @var int
         */
        protected $langId = null;
        
        /**
         * License for this instance
         * @var \Cx\Core_Modules\License\License
         */
        protected $license = null;
        
        /**
         * Wheter APC is enabled or not
         * @var bool
         */
        protected $apcEnabled = false;
        
        /**
         * Contrexx toolbox
         * @todo Update FWSystem
         * @var \FWSystem
         */
        protected $toolbox = null;
        
        /**
         * Initializes the Cx class
         * This does everything related to Contrexx.
         * @param string $mode (optional) Use constants, one of self::MODE_[FRONTEND|BACKEND|CLI|MINIMAL]
         */
        public function __construct($mode = null) {
            try {
                /**
                 * This starts time measurement
                 * Timer will get stopped in finalize() method
                 */
                $this->startTimer();

                /**
                 * Load config/configuration.php, config/settings.php and config/set_constants.php
                 * If you want to load another config instead, you may call
                 * 
                 *     $cx->loadConfig($pathToYourConfigDirectory);
                 *     $cx->handleCustomizing();
                 *     $cx->postInit();
                 *     $cx->loadContrexx();
                 *     // something to stop execution of Cx which does not interrupt the script invoking Cx
                 * 
                 * in the constructor of your ComponentController. 
                 */
                $this->loadConfig();

                /**
                 * Sets the mode Contrexx runs in
                 * One of self::MODE_[FRONTEND|BACKEND|CLI|MINIMAL]
                 */
                $this->setMode($mode);

                /**
                 * Early initializations, tries to enable APC and increase RAM size
                 * This is not a hookscript, since no components are loaded so far
                 */
                $this->preInit();
                
                /**
                 * Loads ClassLoader and Database connection
                 * For now, this also loads some legacy things like API, AdoDB, Env and InitCMS
                 */
                $this->init();
                
                /**
                 * In order to make this file customizable, we explicitly
                 * search for a subclass of Cx\Core\Cx named Cx\Customizing\Core\Cx
                 * If such a class is found, it is loaded and this request will be stopped
                 */
                $this->handleCustomizing();
                
                /**
                 * Load all components to have them ready and initialize request and license
                 */
                $this->postInit();
                
                /**
                 * Since we have a valid state now, we can start executing
                 * all of the component's hook methods.
                 * This initializes the main template, executes all hooks
                 * and parses the template.
                 * 
                 * This is not executed automaticly in minimal mode. Invoke it
                 * yourself if necessary and be sure to handle exceptions.
                 */
                if ($this->mode == self::MODE_MINIMAL) {
                    return;
                }
                $this->loadContrexx();
                
            /**
             * Globally catch all exceptions and show offline.html
             * 
             * This might have one of the following reasons:
             * 1. CMS is disabled by config
             * 2. Frontend is locked by license
             * 3. An error occured
             * 
             * Enable \DBG to see what happened
             */
            } catch (\Exception $e) {
                echo file_get_contents(ASCMS_DOCUMENT_ROOT.'/offline.html');
                \DBG::msg('Contrexx initialization failed! ' . get_class($e) . ': "' . $e->getMessage() . '"');
                die();
            }
        }
        
        /**
         * Initializes global template, executes all component hook methods
         * and parses the template.
         */
        protected function loadContrexx() {
            // init template
            $this->loadTemplate();                      // Sigma Template
            
            // @TODO: remove this
            $this->legacyGlobalsHook(1);                // $objUser, $objTemplate, $cl

            // resolve
            $this->preResolve();                        // Call pre resolve hook scripts
            $this->resolve();                           // Resolving, Language

            // @TODO: remove this
            $this->legacyGlobalsHook(2);                // $objInit, $_LANGID, $_CORELANG, $url;

            $this->postResolve();                       // Call post resolve hook scripts

            // load content
            $this->preContentLoad();                    // Call pre content load hook scripts
            $this->loadContent();                       // Init current module
            $this->postContentLoad();                   // Call post content load hook scripts

            $this->setPostContentLoadPlaceholders();    // Set Placeholders

            $this->preFinalize();                       // Call pre finalize hook scripts
            $this->finalize();                          // Set template vars and display content
            $this->postFinalize();                      // Call post finalize hook scripts
        }

        /**
         * Set the mode Contrexx is used in
         * @param mixed $mode Mode as string or true for front- or false for backend
         */
        protected function setMode($mode) {
            if (php_sapi_name() === 'cli') {
                $this->mode = self::MODE_CLI;
                return;
            }
            switch ($mode) {
                case self::MODE_BACKEND:
                case self::MODE_FRONTEND:
                case self::MODE_CLI:
                case self::MODE_MINIMAL:
                    break;
                default:
                    if ($mode === false) {
                        $mode = self::MODE_BACKEND;
                        break;
                    }
                    $mode = self::MODE_FRONTEND;
                    if (!isset($_GET['__cap'])) {
                        break;
                    }
                    if (!preg_match('#^' . ASCMS_INSTANCE_OFFSET . '(/[a-z]{2})?(/admin|' . ASCMS_BACKEND_PATH . ')#', $_GET['__cap'])) {
                        break;
                    }
                    // this does not belong here:
                    if (!preg_match('#^' . ASCMS_INSTANCE_OFFSET . ASCMS_BACKEND_PATH . '#', $_GET['__cap'])) {
                        // do not use \CSRF::header() here, since ClassLoader is not loaded at this time
                        header('Location: ' . ASCMS_INSTANCE_OFFSET . ASCMS_BACKEND_PATH);
                        die();
                    }
                    $mode = self::MODE_BACKEND;
                    break;
            }
            $this->mode = $mode;
        }
        
        /**
         * Returns the mode this instance of Cx is in
         * @return string One of 'cli', 'frontend', 'backend', 'minimal'
         */
        public function getMode() {
            return $this->mode;
        }
        
        /**
         * Returns the request URL
         * @return \Cx\Core\Routing\Url Request URL
         */
        public function getRequest() {
            return $this->request;
        }
        
        /**
         * Returns the main template
         * @return \Cx\Core\Html\Sigma Main template
         */
        public function getTemplate() {
            return $this->template;
        }
        
        /**
         * Returns the resolved page
         * 
         * Please note, that this works only if mode is self::MODE_FRONTEND by now
         * If resolving has not taken place yet, null is returned
         * @return \Cx\Core\ContentManager\Model\Entity\Page Resolved page or null
         */
        public function getPage() {
            return $this->resolvedPage;
        }
        
        /**
         * Loads configuration files (settings.php and set_constants.php)
         * 
         * configuration.php is loaded in index.php in order to load this file
         * from its correct location.
         * @todo Find a way to store configuration by avoiding global variables
         * @global array $_CONFIG Configuration array from /config/settings.php
         * @global array $_PATHCONFIG Path configuration from /config/configuration.php
         * @throws \Exception If the CMS is deactivated, an exception is thrown
         */
        protected function loadConfig() {
            global $_CONFIG, $_PATHCONFIG;
            
            /**
             * Handle multisite installations
             * CUSTOMIZING from ppay.com
             */
            /*require_once $_PATHCONFIG['ascms_installation_root'].$_PATHCONFIG['ascms_installation_offset'].'/core_modules/MultiSite/Model/Repository/InstanceRepository.class.php';
            require_once $_PATHCONFIG['ascms_installation_root'].$_PATHCONFIG['ascms_installation_offset'].'/core/Component/Model/Entity/EntityBase.class.php';
            require_once $_PATHCONFIG['ascms_installation_root'].$_PATHCONFIG['ascms_installation_offset'].'/core_modules/MultiSite/Model/Entity/Instance.class.php';
            $multiSiteRepo = new \Cx\Core_Modules\MultiSite\Model\Repository\InstanceRepository();
            $subdomain = current(explode('.', $_SERVER['HTTP_HOST']));
            foreach ($multiSiteRepo->findAll('/var/www/trunk2/instances') as $instance) {
                if ($subdomain == strtolower($instance->getName())) {
                    require_once '/var/www/trunk2/instances/'.$instance->getName().'/config/configuration.php';
                    break;
                }
            }*/
            /**
             * End CUSTOMIZING
             */

            /**
             * User configuration settings
             *
             * This file is re-created by the CMS itself. It initializes the
             * {@link $_CONFIG[]} global array.
             */
            $incSettingsStatus = include_once $_PATHCONFIG['ascms_root'].$_PATHCONFIG['ascms_root_offset'].'/config/settings.php';

            /**
             * -------------------------------------------------------------------------
             * Set constants
             * -------------------------------------------------------------------------
             */
            require_once $_PATHCONFIG['ascms_installation_root'].$_PATHCONFIG['ascms_installation_offset'].'/config/set_constants.php';
            
            // Check if system is running
            if ($_CONFIG['systemStatus'] != 'on' && $this->mode == self::MODE_FRONTEND) {
                throw new \Exception('System disabled by config');
            }

            // Check if the system is installed
            if (!defined('CONTEXX_INSTALLED') || !CONTEXX_INSTALLED) {
                \CSRF::header('Location: ../installer/index.php');
                exit;
            } else if ($incSettingsStatus === false) {
                die('System halted: Unable to load basic configuration!');
            }

            // Check if the system is configured with enabled customizings
            if (isset($_CONFIG['useCustomizings']) && $_CONFIG['useCustomizings'] == 'on') {
                $this->customizingPath = ASCMS_CUSTOMIZING_PATH;
            }
        }
        
        /**
         * Loads a subclass of this class from customizing if available
         * @return null
         */
        protected function handleCustomizing() {
            if (!$this->customizingPath) {
                return;
            }
            if (!class_exists('\\Cx\\Customizing\\Core\\Cx')) {
                return;
            }
            // we have to use reflection here, since instanceof does not work if the child is no object
            $myReflection = new \ReflectionClass('\\Cx\\Customizing\\Core\\Cx');
            if (!$myReflection->isSubclassOf(get_class($this))) {
                return;
            }
            new \Cx\Customizing\Core\Cx($this->getMode());
            die();
        }
        
        /**
         * Starts time measurement for page parsing time
         */
        protected function startTimer() {
            $this->startTime = explode(' ', microtime());
        }
        
        /**
         * Stops time measurement and returns page parsing time
         * @return int Time needed to parse page in seconds
         */
        protected function stopTimer() {
            $finishTime = explode(' ', microtime());
            return round(((float)$finishTime[0] + (float)$finishTime[1]) - ((float)$this->startTime[0] + (float)$this->startTime[1]), 5);
        }

        /**
         * Early initializations. Tries to enable APC and increase RAM size
         */
        protected function preInit() {
            $this->tryToEnableApc();
            $this->tryToSetMemoryLimit();
        }

        /**
         * Late initializations. Loads components
         */
        protected function postInit() {
            global $_CONFIG;
            
            $this->loadComponents();
            
            // this makes \Env::get('Resolver')->getUrl() return a sensful result
            $request = !empty($_GET['__cap']) ? $_GET['__cap'] : '';
            $offset = ASCMS_PATH_OFFSET;
            if ($this->mode == self::MODE_BACKEND) {
                $request = ASCMS_PATH_OFFSET.'/cadmin';
                $offset = ASCMS_INSTANCE_OFFSET;
            }
            $this->request = \Cx\Core\Routing\Url::fromCapturedRequest($request, $offset, $_GET);
            $this->license = \Cx\Core_Modules\License\License::getCached($_CONFIG, $this->getDb()->getAdoDb());
        }

        /**
         * Calls pre-resolve hooks
         */
        protected function preResolve() {
            $this->ch->callPreResolveHooks();
        }

        /**
         * Does the resolving
         * 
         * For modes other than 'frontend', no actual resolving is done,
         * resolver is just initialized in order to return the correct result
         * for $resolver->getUrl()
         * @todo Implement resolver for backend
         * @todo Is this useful in CLI mode?
         */
        protected function resolve() {
            $this->resolver = new \Cx\Core\Routing\Resolver($this->getRequest(), null, $this->getDb()->getEntityManager(), null, null);
            
            if ($this->mode == self::MODE_FRONTEND) {
                $this->resolvedPage = $this->resolver->resolve();
                $this->resolvedPage->setVirtual(true);
                
            } else {
                global $cmd, $act, $isRegularPageRequest, $plainCmd;

                $this->resolvedPage = new \Cx\Core\ContentManager\Model\Entity\Page();
                $this->resolvedPage->setVirtual(true);
                
                if (!isset($plainCmd)) {
                    $cmd = isset($_REQUEST['cmd']) ? $_REQUEST['cmd'] : '';
                    $act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
                    $plainCmd = $cmd;
                }
                
                // If standalone is set, then we will not have to initialize/load any content page related stuff
                $isRegularPageRequest = !isset($_REQUEST['standalone']) || $_REQUEST['standalone'] == 'false';
            }
        }

        /**
         * Calls post-resolve hooks
         * @todo Remove usage of globals
         * @global \Cx\Core\ContentManager\Model\Entity\Page $page Resolved page
         * @global string $page_title Resolved page's title
         */
        protected function postResolve() {
            global $page, $page_title;
            
            $this->ch->callPostResolveHooks('legacy');
            if ($page) {
                $this->resolvedPage = $page;
            }
            if ($this->resolvedPage) {
                $this->resolvedPage->setContentTitle($page_title);
            }
            $this->ch->callPostResolveHooks('proper');
            if ($this->resolvedPage) {
                $page_title = $this->resolvedPage->getContentTitle();
            }
        }

        /**
         * Calls hooks before content is processed
         * @todo Remove usage of globals
         * @global string $page_title
         * @global boolean $boolShop
         * @global null $moduleStyleFile
         * @global type $plainCmd
         * @global type $plainSection 
         */
        protected function preContentLoad() {
            global $page_title, $boolShop, $moduleStyleFile,
                    $plainCmd, $plainSection;
            
            $this->ch->callPreContentLoadHooks();
            if ($this->resolvedPage) {
                $page_title = $this->resolvedPage->getContentTitle();
                $pageContent = $this->resolvedPage->getContent();
            }
            
            if ($this->mode == self::MODE_FRONTEND) {
                $this->setPreContentLoadPlaceholders($this->template);        
                //replace the {NODE_<ID>_<LANG>}- placeholders
                \LinkGenerator::parseTemplate($pageContent);
                $this->resolvedPage->setContent($pageContent);

                $boolShop = false;
                $moduleStyleFile = null;
            } else if ($this->mode == self::MODE_BACKEND) {
                // Skip the nav/language bar for modules which don't make use of either.
                // TODO: Remove language selector for modules which require navigation but bring their own language management.
                if (in_array($plainCmd, array('content'))) {
                    $this->template->addBlockfile('CONTENT_OUTPUT', 'content_master', 'content_master_stripped.html');
                } else {
                    $this->template->addBlockfile('CONTENT_OUTPUT', 'content_master', 'content_master.html');
                }
                $plainSection = $plainCmd;
            }
        }

        /**
         * Calls hooks after content was processed
         */
        protected function postContentLoad() {
            $this->ch->callPostContentLoadHooks();
        }
        
        /**
         * Calls hooks before finalize() is called
         */
        protected function preFinalize() {
            $this->ch->callPreFinalizeHooks();
        }
        
        /**
         * Calls hooks after call to finalize()
         */
        protected function postFinalize() {
            $this->ch->callPostFinalizeHooks();
        }

        /**
         * This tries to enable Alternate PHP Cache
         */
        protected function tryToEnableApc() {
            $this->apcEnabled = false;
            if (extension_loaded('apc')) {
                if (ini_get('apc.enabled')) {
                    $this->apcEnabled = true;
                } else {
                    ini_set('apc.enabled', 1);
                    if (ini_get('apc.enabled')) {
                        $this->apcEnabled = true;
                    }
                }
            }
        }

        /**
         * This tries to set the memory limit if its lower than 32 megabytes
         */
        protected function tryToSetMemoryLimit() {
            $memoryLimit = array();
            preg_match('/^\d+/', ini_get('memory_limit'), $memoryLimit);
            $this->memoryLimit = $memoryLimit[0];
            if ($this->apcEnabled) {
                if ($this->memoryLimit < 32) {
                    ini_set('memory_limit', '32M');
                }
            } else {
                if ($this->memoryLimit < 48) {
                    ini_set('memory_limit', '48M');
                }
            }
        }

        /**
         * Loads all active components
         */
        protected function loadComponents() {
            $this->ch = new \Cx\Core\Component\Controller\ComponentHandler($this->mode == self::MODE_FRONTEND, $this->db->getEntityManager());
            $this->ch->initComponents();
        }
        
        /**
         * Returns the current user object 
         * @return \FWUser Current user
         */
        public function getUser() {
            return \FWUser::getFWUserObject();
        }
        
        /**
         * Tells wheter APC is enabled or not
         * @return boolean True if APC is enabled, false otherwise
         */
        public function isApcEnabled() {
            return $this->apcEnabled;
        }
        
        /**
         * Returns the toolbox
         * @return \FWSystem Toolbox
         */
        public function getToolbox() {
            if (!$this->toolbox) {
                $this->toolbox = new \FWSystem();
            }
            return $this->toolbox;
        }
        
        /**
         * Returns the database connection handler
         * @return \Cx\Core\Db\Db DB connection handler
         */
        public function getDb() {
            return $this->db;
        }
        
        /**
         * Returns the license for this instance
         * @return \Cx\Core_Modules\License\License
         */
        public function getLicense() {
            return $this->license;
        }
        
        /**
         * Init main template object
         * 
         * In backend mode, ASCMS_ADMIN_TEMPLATE_PATH/index.html is opened
         * In all other modes, no file is loaded here
         */
        protected function loadTemplate() {
            $this->template = new \Cx\Core\Html\Sigma(($this->mode == self::MODE_FRONTEND) ? ASCMS_THEMES_PATH : ASCMS_ADMIN_TEMPLATE_PATH);
            $this->template->setErrorHandling(PEAR_ERROR_DIE);
            if ($this->mode == self::MODE_BACKEND) {
                $this->template->loadTemplateFile('index.html');
                $this->template->addBlockfile('CONTENT_FILE', 'index_content', 'index_content.html');
            }
        }
        
        /**
         * This populates globals for legacy code
         * @todo Avoid this! All this should be part of some components hook
         * @global type $objFWUser
         * @global type $objTemplate
         * @global type $cl
         * @global \InitCMS $objInit
         * @global type $_LANGID
         * @global type $_CORELANG
         * @global \Cx\Core\Routing\Url $url
         * @param type $no 
         */
        protected function legacyGlobalsHook($no) {
            global $objFWUser, $objTemplate, $cl, $objInit, $_LANGID, $_CORELANG, $url;
            
            switch ($no) {
                case 1:
                    // Request URL
                    $url = $this->request;
                    // Get instance of FWUser object
                    $objFWUser = $this->getUser();
                    // populate template
                    $objTemplate = $this->template;
                    // populate classloader
                    $cl = $this->cl;
                    break;
                
                case 2:
                    // Code to set language
                    // @todo: move this to somewhere else
                    // in backend it's in Language->postResolve
                    if ($this->mode == self::MODE_FRONTEND) {
                        $_LANGID = FRONTEND_LANG_ID;
                        $objInit->setFrontendLangId($_LANGID);
                        define('LANG_ID', $_LANGID);
                        // Load interface language data
                        /**
                        * Core language data
                        * @global array $_CORELANG
                        */
                        $_CORELANG = $objInit->loadLanguageData('core');
                    }
                    
                    \Env::set('Resolver', $this->resolver);

                    // Resolver code
                    // @todo: move to resolver
                    //expose the virtual language directory to the rest of the cms
                    $virtualLanguageDirectory = '/'.$url->getLangDir();
                    \Env::set('virtualLanguageDirectory', $virtualLanguageDirectory);
                    // TODO: this constanst used to be located in config/set_constants.php, but needed to be relocated to this very place,
                    // because it depends on Env::get('virtualLanguageDirectory').
                    // Find an other solution; probably best is to replace CONTREXX_SCRIPT_PATH by a prettier method
                    define('CONTREXX_SCRIPT_PATH',
                        ASCMS_PATH_OFFSET.
                        \Env::get('virtualLanguageDirectory').
                        '/'.
                        CONTREXX_DIRECTORY_INDEX);
                    break;
            }
        }

        /**
         * Loading ClassLoader, Env, DB, API and InitCMS
         * (Env, API and InitCMS are deprecated)
         * @todo Remove deprecated elements
         * @todo Remove usage of globals
         * @global array $_CONFIG
         * @global type $_FTPCONFIG
         * @global type $objDatabase
         * @global type $objInit 
         */
        protected function init() {
            global $_CONFIG, $_FTPCONFIG, $objDatabase, $objInit;

            /**
             * This needs to be initialized before loading config/doctrine.php
             * Because we overwrite the Gedmo model (so we need to load our model
             * before doctrine loads the Gedmo one)
             */
            require_once(ASCMS_CORE_PATH.'/ClassLoader/ClassLoader.class.php');
            $this->cl = new \Cx\Core\ClassLoader\ClassLoader(ASCMS_DOCUMENT_ROOT, true, $this->customizingPath);

            /**
             * Environment repository
             */
            require_once($this->cl->getFilePath(ASCMS_CORE_PATH.'/Env.class.php'));
            \Env::set('cx', $this);
            \Env::set('ClassLoader', $this->cl);            
            \Env::set('config', $_CONFIG);
            \Env::set('ftpConfig', $_FTPCONFIG);

            /**
             * Include all the required files.
             * @todo Remove API.php, it should be unnecessary
             */
            $this->cl->loadFile(ASCMS_CORE_PATH.'/API.php');
            // Temporary fix until all GET operation requests will be replaced by POSTs
            \CSRF::setFrontendMode();

            $this->db = new \Cx\Core\Db\Db($this);
            $objDatabase = $this->db->getAdoDb();
            \Env::set('db', $objDatabase);
            $em = $this->db->getEntityManager();
            \Env::set('em', $em);
            \Env::set('pageguard', new \PageGuard($this->db->getAdoDb()));

            \DBG::set_adodb_debug_mode();

            // Initialize base system
            // TODO: Get rid of InitCMS class, merge it with this class instead
            $objInit = new \InitCMS($this->mode == self::MODE_FRONTEND ? 'frontend' : 'backend', \Env::em());
            \Env::set('init', $objInit);
        }

        /**
         * This parses the content
         * 
         * This cannot be used in mode self::MODE_CLI, since content is added to template directly
         * @todo Write a method, that only returns the content, in order to allow usage in CLI mode
         * @todo Remove usage of globals
         * @global type $plainSection
         * @global type $_CORELANG
         * @global type $subMenuTitle
         * @global type $act
         * @global type $_ARRAYLANG 
         */
        protected function loadContent() {
            global $plainSection, $_CORELANG,
                    $subMenuTitle, $act, $_ARRAYLANG;
            
            if ($this->mode == self::MODE_CLI) {
                return;
            }
            
            // init module language
            $_ARRAYLANG = \Env::get('init')->loadLanguageData($plainSection);
            
            // load module
            try {
                // This is the nice way
                $this->ch->loadComponent($this, $plainSection, $this->resolvedPage);
            } catch (\Cx\Core\Component\Controller\ComponentException $e) {
                
                // this only happens in backend, add load exceptions for backend to avoid this
                
                $moduleManager = new \modulemanager();
                // This is necessary to avoid notices, since those are passed by reference
                $adodb = $this->getDb()->getAdoDb();
                $em = $this->getDb()->getEntityManager();
                $user = $this->getUser();
                $init = \Env::get('init');
                
                try {
                    // This is the semi nice way
                    $moduleManager->loadModule(
                        $plainSection,
                        $this->cl,
                        $adodb,
                        $_CORELANG,
                        $subMenuTitle,
                        $this->template,
                        $user,
                        $act,
                        $init,
                        $_ARRAYLANG,
                        $em,
                        $this
                    );
                } catch (\ModuleManagerException $e) {
                    // This is the old fashion way
                    $moduleManager->loadLegacyModule(
                        $plainSection,
                        $this->cl,
                        $adodb,
                        $_CORELANG,
                        $subMenuTitle,
                        $this->template,
                        $user,
                        $act,
                        $init,
                        $_ARRAYLANG
                    );
                }
            }
        }
        
        /**
         * Set main template placeholders required before parsing the content
         * @todo Does this even make any sense? Couldn't simply everything be set after content parsing?
         * @todo Remove usage of globals
         * @global type $themesPages
         * @global type $page_template
         * @global array $_CONFIG
         * @global string $page_title
         * @param type $objTemplate 
         */
        protected function setPreContentLoadPlaceholders($objTemplate) {
            global $themesPages, $page_template, $_CONFIG, $page_title;

            $objTemplate->setTemplate($themesPages['index']);
            $objTemplate->addBlock('CONTENT_FILE', 'page_template', $page_template);

            // Set global content variables.
            $pageContent = $this->resolvedPage->getContent();
            $pageContent = str_replace('{PAGE_URL}',        htmlspecialchars(\Env::get('init')->getPageUri()), $pageContent);
            $pageContent = str_replace('{STANDARD_URL}',    \Env::get('init')->getUriBy('smallscreen', 0),     $pageContent);
            $pageContent = str_replace('{MOBILE_URL}',      \Env::get('init')->getUriBy('smallscreen', 1),     $pageContent);
            $pageContent = str_replace('{PRINT_URL}',       \Env::get('init')->getUriBy('printview', 1),       $pageContent);
            $pageContent = str_replace('{PDF_URL}',         \Env::get('init')->getUriBy('pdfview', 1),         $pageContent);
            $pageContent = str_replace('{APP_URL}',         \Env::get('init')->getUriBy('appview', 1),         $pageContent);
            $pageContent = str_replace('{LOGOUT_URL}',      \Env::get('init')->getUriBy('section', 'logout'),  $pageContent);
            $pageContent = str_replace('{TITLE}',           $page_title, $pageContent);
            $pageContent = str_replace('{CONTACT_EMAIL}',   isset($_CONFIG['contactFormEmail']) ? contrexx_raw2xhtml($_CONFIG['contactFormEmail']) : '', $pageContent);
            $pageContent = str_replace('{CONTACT_COMPANY}', isset($_CONFIG['contactCompany'])   ? contrexx_raw2xhtml($_CONFIG['contactCompany'])   : '', $pageContent);
            $pageContent = str_replace('{CONTACT_ADDRESS}', isset($_CONFIG['contactAddress'])   ? contrexx_raw2xhtml($_CONFIG['contactAddress'])   : '', $pageContent);
            $pageContent = str_replace('{CONTACT_ZIP}',     isset($_CONFIG['contactZip'])       ? contrexx_raw2xhtml($_CONFIG['contactZip'])       : '', $pageContent);
            $pageContent = str_replace('{CONTACT_PLACE}',   isset($_CONFIG['contactPlace'])     ? contrexx_raw2xhtml($_CONFIG['contactPlace'])     : '', $pageContent);
            $pageContent = str_replace('{CONTACT_COUNTRY}', isset($_CONFIG['contactCountry'])   ? contrexx_raw2xhtml($_CONFIG['contactCountry'])   : '', $pageContent);
            $pageContent = str_replace('{CONTACT_PHONE}',   isset($_CONFIG['contactPhone'])     ? contrexx_raw2xhtml($_CONFIG['contactPhone'])     : '', $pageContent);
            $pageContent = str_replace('{CONTACT_FAX}',     isset($_CONFIG['contactFax'])       ? contrexx_raw2xhtml($_CONFIG['contactFax'])       : '', $pageContent);
            $this->resolvedPage->setContent($pageContent);
        }

        /**
         * Set main template placeholders required after content parsing
         * @todo Remove usage of globals
         * @global array $_CONFIG
         * @global type $themesPages
         * @global boolean $boolShop
         * @global type $objCounter
         * @global type $objBanner
         * @global type $_CORELANG
         * @return type 
         */
        protected function setPostContentLoadPlaceholders() {
            global $_CONFIG, $themesPages, $boolShop, $objCounter, $objBanner, $_CORELANG;

            if ($this->mode == self::MODE_BACKEND) {
                $this->template->setGlobalVariable(array(
                    'TXT_FRONTEND'              => $_CORELANG['TXT_FRONTEND'],
                    'TXT_UPGRADE'               => $_CORELANG['TXT_UPGRADE'],
                ));
                $this->template->setVariable(array(
                    'TXT_LOGOUT'                => $_CORELANG['TXT_LOGOUT'],
                    'TXT_PAGE_ID'               => $_CORELANG['TXT_PAGE_ID'],
                    'CONTAINER_BACKEND_CLASS'   => 'backend',
                    'CONTREXX_CHARSET'          => CONTREXX_CHARSET,
                ));
                return;
            }
            
            // set global template variables
            $objNavbar = new \Navigation($this->resolvedPage->getId(), $this->resolvedPage);
            $objNavbar->setLanguagePlaceholders($this->resolvedPage, $this->request, $this->template);
            $this->template->setVariable(array(
                'CHARSET'                        => \Env::get('init')->getFrontendLangCharset(),
                'TITLE'                          => $this->resolvedPage->getTitle(),
                'METATITLE'                      => $this->resolvedPage->getMetatitle(),
                'NAVTITLE'                       => contrexx_raw2xhtml($this->resolvedPage->getTitle()),
                'GLOBAL_TITLE'                   => $_CONFIG['coreGlobalPageTitle'],
                'DOMAIN_URL'                     => $_CONFIG['domainUrl'],
                'PATH_OFFSET'                    => ASCMS_PATH_OFFSET,
                'BASE_URL'                       => ASCMS_PROTOCOL.'://'.$_CONFIG['domainUrl'].ASCMS_PATH_OFFSET,
                'METAKEYS'                       => contrexx_raw2xhtml($this->resolvedPage->getMetakeys()),
                'METADESC'                       => contrexx_raw2xhtml($this->resolvedPage->getMetadesc()),
                'METAROBOTS'                     => contrexx_raw2xhtml($this->resolvedPage->getMetarobots()),
                'CONTENT_TITLE'                  => $this->resolvedPage->getTitle(),
                'CONTENT_TEXT'                   => $this->resolvedPage->getContent(),
                'CSS_NAME'                       => $this->resolvedPage->getCssName(),
                'STANDARD_URL'                   => \Env::get('init')->getUriBy('smallscreen', 0),
                'MOBILE_URL'                     => \Env::get('init')->getUriBy('smallscreen', 1),
                'PRINT_URL'                      => \Env::get('init')->getUriBy('printview', 1),
                'PDF_URL'                        => \Env::get('init')->getUriBy('pdfview', 1),
                'APP_URL'                        => \Env::get('init')->getUriBy('appview', 1),
                'LOGOUT_URL'                     => \Env::get('init')->getUriBy('section', 'logout'),
                'PAGE_URL'                       => htmlspecialchars(\Env::get('init')->getPageUri()),
                'CURRENT_URL'                    => \Env::get('init')->getCurrentPageUri(),
                'DATE'                           => showFormattedDate(),
                'TIME'                           => date('H:i', time()),
                'NAVTREE'                        => $objNavbar->getTrail(),
                'SUBNAVBAR_FILE'                 => $objNavbar->getSubnavigation($themesPages['subnavbar'], $this->license, $boolShop),
                'SUBNAVBAR2_FILE'                => $objNavbar->getSubnavigation($themesPages['subnavbar2'], $this->license, $boolShop),
                'SUBNAVBAR3_FILE'                => $objNavbar->getSubnavigation($themesPages['subnavbar3'], $this->license, $boolShop),
                'NAVBAR_FILE'                    => $objNavbar->getNavigation($themesPages['navbar'], $this->license, $boolShop),
                'NAVBAR2_FILE'                   => $objNavbar->getNavigation($themesPages['navbar2'], $this->license, $boolShop),
                'NAVBAR3_FILE'                   => $objNavbar->getNavigation($themesPages['navbar3'], $this->license, $boolShop),
                'ONLINE_USERS'                   => $objCounter->getOnlineUsers(),
                'VISITOR_NUMBER'                 => $objCounter->getVisitorNumber(),
                'COUNTER'                        => $objCounter->getCounterTag(),
                'BANNER'                         => isset($objBanner) ? $objBanner->getBannerJS() : '',
                'VERSION'                        => contrexx_raw2xhtml($_CONFIG['coreCmsName']),
                'LANGUAGE_NAVBAR'                => $objNavbar->getFrontendLangNavigation($this->resolvedPage, $this->request),
                'LANGUAGE_NAVBAR_SHORT'          => $objNavbar->getFrontendLangNavigation($this->resolvedPage, $this->request, true),
                'ACTIVE_LANGUAGE_NAME'           => \Env::get('init')->getFrontendLangName(),
                'RANDOM'                         => md5(microtime()),
                'TXT_SEARCH'                     => $_CORELANG['TXT_SEARCH'],
                'MODULE_INDEX'                   => MODULE_INDEX,
                'LOGIN_URL'                      => '<a href="' . \Env::get('init')->getUriBy('section', 'login') . '">' . $_CORELANG['TXT_FRONTEND_EDITING_LOGIN'] . '</a>',
                'JAVASCRIPT'                     => 'javascript_inserting_here',
                'TXT_CORE_LAST_MODIFIED_PAGE'    => $_CORELANG['TXT_CORE_LAST_MODIFIED_PAGE'],
                'LAST_MODIFIED_PAGE'             => date(ASCMS_DATE_FORMAT_DATE, $this->resolvedPage->getUpdatedAt()->getTimestamp()),
                'CONTACT_EMAIL'                  => isset($_CONFIG['contactFormEmail']) ? contrexx_raw2xhtml($_CONFIG['contactFormEmail']) : '',
                'CONTACT_COMPANY'                => isset($_CONFIG['contactCompany'])   ? contrexx_raw2xhtml($_CONFIG['contactCompany'])   : '',
                'CONTACT_ADDRESS'                => isset($_CONFIG['contactAddress'])   ? contrexx_raw2xhtml($_CONFIG['contactAddress'])   : '',
                'CONTACT_ZIP'                    => isset($_CONFIG['contactZip'])       ? contrexx_raw2xhtml($_CONFIG['contactZip'])       : '',
                'CONTACT_PLACE'                  => isset($_CONFIG['contactPlace'])     ? contrexx_raw2xhtml($_CONFIG['contactPlace'])     : '',
                'CONTACT_COUNTRY'                => isset($_CONFIG['contactCountry'])   ? contrexx_raw2xhtml($_CONFIG['contactCountry'])   : '',
                'CONTACT_PHONE'                  => isset($_CONFIG['contactPhone'])     ? contrexx_raw2xhtml($_CONFIG['contactPhone'])     : '',
                'CONTACT_FAX'                    => isset($_CONFIG['contactFax'])       ? contrexx_raw2xhtml($_CONFIG['contactFax'])       : '',
                'FACEBOOK_LIKE_IFRAME'           => '<div id="fb-root"></div>
                                                    <script type="text/javascript">
                                                        (function(d, s, id) {
                                                            var js, fjs = d.getElementsByTagName(s)[0];
                                                            if (d.getElementById(id)) return;
                                                            js = d.createElement(s); js.id = id;
                                                            js.src = "//connect.facebook.net/de_DE/all.js#xfbml=1";
                                                            fjs.parentNode.insertBefore(js, fjs);
                                                        }(document, \'script\', \'facebook-jssdk\'));
                                                    </script>
                                                    <div class="fb-like" data-href="http://'.$_CONFIG['domainUrl'].\Env::get('init')->getCurrentPageUri().'" data-send="false" data-layout="button_count" data-show-faces="false" data-font="segoe ui"></div>',
                'GOOGLE_PLUSONE'                 => '<div class="g-plusone" data-href="http://'.$_CONFIG['domainUrl'].\Env::get('init')->getCurrentPageUri().'"></div>
                                                    <script type="text/javascript">
                                                        window.___gcfg = {lang: \'de\'};

                                                        (function() {
                                                            var po = document.createElement(\'script\'); po.type = \'text/javascript\'; po.async = true;
                                                            po.src = \'https://apis.google.com/js/plusone.js\';
                                                            var s = document.getElementsByTagName(\'script\')[0]; s.parentNode.insertBefore(po, s);
                                                        })();
                                                    </script>',
                'GOOGLE_ANALYTICS'               => '<script type="text/javascript">
                                                        var _gaq = _gaq || [];
                                                        _gaq.push([\'_setAccount\', \''.(isset($_CONFIG['googleAnalyticsTrackingId']) ? contrexx_raw2xhtml($_CONFIG['googleAnalyticsTrackingId']) : '').'\']);
                                                        _gaq.push([\'_trackPageview\']);

                                                        (function() {
                                                            var ga = document.createElement(\'script\'); ga.type = \'text/javascript\'; ga.async = true;
                                                            ga.src = (\'https:\' == document.location.protocol ? \'https://ssl\' : \'http://www\') + \'.google-analytics.com/ga.js\';
                                                            var s = document.getElementsByTagName(\'script\')[0]; s.parentNode.insertBefore(ga, s);
                                                        })();
                                                    </script>',
            ));
        }

        /**
         * Parses the main template in order to finish request
         * @todo Remove usage of globals
         * @global type $themesPages
         * @global null $moduleStyleFile
         * @global type $objCache
         * @global array $_CONFIG
         * @global \InitCMS $objInit
         * @global string $page_title
         * @global type $subMenuTitle
         * @global type $_CORELANG
         * @global type $objFWUser
         * @global type $plainCmd
         * @global type $cmd
         */
        protected function finalize() {
            global $themesPages, $moduleStyleFile, $objCache, $_CONFIG, $objInit,
                    $page_title, $subMenuTitle, $_CORELANG, $objFWUser, $plainCmd, $cmd;

            if ($this->mode == self::MODE_FRONTEND) {
                // parse system
                $time = $this->stopTimer();
                $this->template->setVariable('PARSING_TIME', $time);

                $themesPages['sidebar'] = str_replace('{STANDARD_URL}',    $objInit->getUriBy('smallscreen', 0),    $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{MOBILE_URL}',      $objInit->getUriBy('smallscreen', 1),    $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{PRINT_URL}',       $objInit->getUriBy('printview', 1),      $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{PDF_URL}',         $objInit->getUriBy('pdfview', 1),        $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{APP_URL}',         $objInit->getUriBy('appview', 1),        $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{LOGOUT_URL}',      $objInit->getUriBy('section', 'logout'), $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{CONTACT_EMAIL}',   isset($_CONFIG['contactFormEmail']) ? contrexx_raw2xhtml($_CONFIG['contactFormEmail']) : '', $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{CONTACT_COMPANY}', isset($_CONFIG['contactCompany'])   ? contrexx_raw2xhtml($_CONFIG['contactCompany'])   : '', $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{CONTACT_ADDRESS}', isset($_CONFIG['contactAddress'])   ? contrexx_raw2xhtml($_CONFIG['contactAddress'])   : '', $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{CONTACT_ZIP}',     isset($_CONFIG['contactZip'])       ? contrexx_raw2xhtml($_CONFIG['contactZip'])       : '', $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{CONTACT_PLACE}',   isset($_CONFIG['contactPlace'])     ? contrexx_raw2xhtml($_CONFIG['contactPlace'])     : '', $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{CONTACT_COUNTRY}', isset($_CONFIG['contactCountry'])   ? contrexx_raw2xhtml($_CONFIG['contactCountry'])   : '', $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{CONTACT_PHONE}',   isset($_CONFIG['contactPhone'])     ? contrexx_raw2xhtml($_CONFIG['contactPhone'])     : '', $themesPages['sidebar']);
                $themesPages['sidebar'] = str_replace('{CONTACT_FAX}',     isset($_CONFIG['contactFax'])       ? contrexx_raw2xhtml($_CONFIG['contactFax'])       : '', $themesPages['sidebar']);

                $this->template->setVariable(array(
                    'SIDEBAR_FILE' => $themesPages['sidebar'],
                    'JAVASCRIPT_FILE' => $themesPages['javascript'],
                    'BUILDIN_STYLE_FILE' => $themesPages['buildin_style'],
                    'DATE_YEAR' => date('Y'),
                    'DATE_MONTH' => date('m'),
                    'DATE_DAY' => date('d'),
                    'DATE_TIME' => date('H:i'),
                    'BUILDIN_STYLE_FILE' => $themesPages['buildin_style'],
                    'JAVASCRIPT_LIGHTBOX' =>
                        '<script type="text/javascript" src="lib/lightbox/javascript/mootools.js"></script>
                        <script type="text/javascript" src="lib/lightbox/javascript/slimbox.js"></script>',
                    'JAVASCRIPT_MOBILE_DETECTOR' =>
                        '<script type="text/javascript" src="lib/mobiledetector.js"></script>',
                ));

                if (!empty($moduleStyleFile))
                    $this->template->setVariable(
                        'STYLE_FILE',
                        "<link rel=\"stylesheet\" href=\"$moduleStyleFile\" type=\"text/css\" media=\"screen, projection\" />"
                    );

                if (isset($_GET['pdfview']) && intval($_GET['pdfview']) == 1) {
                    $this->cl->loadFile(ASCMS_CORE_PATH.'/pdf.class.php');
                    $objPDF          = new PDF();
                    $objPDF->title   = $page_title.(empty($page_title) ? null : '.pdf');
                    $objPDF->content = $this->template->get();
                    $objPDF->Create();
                    exit;
                }

                //enable gzip compressing of the output - up to 75% smaller responses!
                //commented out because of certain php.inis generating a
                //WARNING: ob_start(): output handler 'ob_gzhandler' cannot be used after 'URL-Rewriter
                //ob_start("ob_gzhandler");

                // fetch the parsed webpage
                $endcode = $this->template->get();

                /**
                 * Get all javascripts in the code, replace them with nothing, and register the js file
                 * to the javascript lib. This is because we don't want something twice, and there could be
                 * a theme that requires a javascript, which then could be used by a module too and therefore would
                 * be loaded twice.
                 */
                /* Finds all uncommented script tags, strips them out of the HTML and
                 * stores them internally so we can put them in the placeholder later
                 * (see JS::getCode() below)
                 */
                \JS::findJavascripts($endcode);
                /*
                 * Proposal:  Use this
                 *     $endcode = preg_replace_callback('/<script\s.*?src=(["\'])(.*?)(\1).*?\/?>(?:<\/script>)?/i', array('JS', 'registerFromRegex'), $endcode);
                 * and change JS::registerFromRegex to use index 2
                 */
                // i know this is ugly, but is there another way
                $endcode = str_replace('javascript_inserting_here', \JS::getCode(), $endcode);

                // do a final replacement of all those node-urls ({NODE_<ID>_<LANG>}- placeholders) that haven't been captured earlier
                $endcode = preg_replace('/\\[\\[([A-Z0-9_-]+)\\]\\]/', '{\\1}', $endcode);
                \LinkGenerator::parseTemplate($endcode);

                // replace links from before contrexx 3
                $ls = new \LinkSanitizer(
                    ASCMS_PATH_OFFSET.\Env::get('virtualLanguageDirectory').'/',
                    $endcode);
                $endcode = $ls->replace();

                echo $endcode;

                $objCache->endCache();
            } else {
                // page parsing
                $parsingTime = $this->stopTimer();
//                var_dump($parsingTime);
    /*echo ($finishTime[0] - $startTime[0]) . '<br />';
    if (!isset($_SESSION['asdf1']) || isset($_GET['reset'])) {
        $_SESSION['asdf1'] = 0;
        $_SESSION['asdf2'] = 0;
    }
    echo $_SESSION['asdf1'] . '<br />';
    if ($_SESSION['asdf1'] > 0) {
        echo $_SESSION['asdf2'] / $_SESSION['asdf1'];
    }
    $_SESSION['asdf1']++;
    $_SESSION['asdf2'] += ($finishTime[0] - $startTime[0]);//*/
                $objAdminNav = new \adminMenu($plainCmd);
                $objAdminNav->getAdminNavbar();
                $this->template->setVariable(array(
                    'SUB_MENU_TITLE' => $subMenuTitle,
                    'FRONTEND_LANG_MENU' => $objInit->getUserFrontendLangMenu(),
                    'TXT_GENERATED_IN' => $_CORELANG['TXT_GENERATED_IN'],
                    'TXT_SECONDS' => $_CORELANG['TXT_SECONDS'],
                    'TXT_LOGOUT_WARNING' => $_CORELANG['TXT_LOGOUT_WARNING'],
                    'PARSING_TIME'=> $parsingTime,
                    'LOGGED_NAME' => htmlentities($objFWUser->objUser->getProfileAttribute('firstname').' '.$objFWUser->objUser->getProfileAttribute('lastname'), ENT_QUOTES, CONTREXX_CHARSET),
                    'TXT_LOGGED_IN_AS' => $_CORELANG['TXT_LOGGED_IN_AS'],
                    'TXT_LOG_OUT' => $_CORELANG['TXT_LOG_OUT'],
                // TODO: This function call returns the empty string -- always!  What's the use?
                //    'CONTENT_WYSIWYG_CODE' => get_wysiwyg_code(),
                    // Mind: The module index is not used in any non-module template
                    // for the time being, but is provided for future use and convenience.
                    'MODULE_INDEX' => MODULE_INDEX,
                    // The Shop module for one heavily uses custom JS code that is properly
                    // handled by that class -- finally
                    'JAVASCRIPT' => \JS::getCode(),
                ));


                // Style parsing
                if (file_exists(ASCMS_ADMIN_TEMPLATE_PATH.'/css/'.$cmd.'.css')) {
                    // check if there's a css file in the core section
                    $this->template->setVariable('ADD_STYLE_URL', ASCMS_ADMIN_TEMPLATE_WEB_PATH.'/css/'.$cmd.'.css');
                    $this->template->parse('additional_style');
                } elseif (file_exists(ASCMS_MODULE_PATH.'/'.$cmd.'/template/backend.css')) {
                    // of maybe in the current module directory
                    $this->template->setVariable('ADD_STYLE_URL', ASCMS_MODULE_WEB_PATH.'/'.$cmd.'/template/backend.css');
                    $this->template->parse('additional_style');
                } elseif (file_exists(ASCMS_CORE_MODULE_PATH.'/'.$cmd.'/template/backend.css')) {
                    // or in the core module directory
                    $this->template->setVariable('ADD_STYLE_URL', ASCMS_CORE_MODULE_WEB_PATH.'/'.$cmd.'/template/backend.css');
                    $this->template->parse('additional_style');
                } else {
                    $this->template->hideBlock('additional_style');
                }


                //enable gzip compressing of the output - up to 75% smaller responses!
                //commented out because of certain php.inis generating a 
                //WARNING: ob_start(): output handler 'ob_gzhandler' cannot be used after 'URL-Rewriter
                //ob_start("ob_gzhandler");

                $this->template->show();
                /*echo '<pre>';
                print_r($_SESSION);
                /*echo '<b>Overall time: ' . (microtime(true) - $timeAtStart) . 's<br />';
                echo 'Max RAM usage: ' . formatBytes(memory_get_peak_usage()) . '<br />';
                echo 'End RAM usage: ' . formatBytes(memory_get_usage()) . '<br /></b>';*/
            }
        }
    }
}
