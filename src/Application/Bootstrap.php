<?php

namespace Logikos\Application;

use Logikos\Application\Bootstrap\Modules;
use Logikos\Application\Bootstrap\Paths;
use Phalcon\Config;
use Phalcon\Di;
use Phalcon\Di\Injectable;
use Phalcon\Di\FactoryDefault;
use Phalcon\DiInterface;
use Phalcon\Error\Handler as ErrorHandler;
use Phalcon\Events\Event;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Loader;
use Phalcon\Logger\Adapter\File as FileLogger;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Model\Manager as ModelsManager;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Url as UrlResolver;
use Phalcon\Mvc\ViewInterface;
use Phalcon\Registry;

class Bootstrap extends Injectable {
  //use \Logikos\UserOptionTrait;
  
  private $_defaultOptions = [
      'basedir'  => null,
      'confdir'  => null,
      'appdir'   => null,
      'config'   => null,
      'app'      => null,
      'eventsManager' => null,
      'env'      => 'development',
      'defaultModule' => null
  ];
  
  /**
   * @var Application
   */
  public $app;
  
  /**
   * @var Config
   */
  public static $config;
  
  /**
   * @var Modules
   */
  public $modules;
  
  /**
   * @var Registry
   */
  public $userOptions;
  
  const EVENT_PREFIX    = 'ltboot';
  const ENV_PRODUCTION  = 'production';
  const ENV_STAGING     = 'staging';
  const ENV_DEVELOPMENT = 'development';
  const ENV_TESTING     = 'testing';
  
  public static $testing = false;
  
  public function __construct(array $userOptions = null, $config = null, DiInterface $di = null) {
    $this->initOptions($userOptions);
    $this->initPaths();
    $this->loadEnv();
    $this->initConfig($config);
    if (!is_null($di)) {
      $this->setDI($di);
    }
  }

  public function setDI(DiInterface $di) {
    $di->setShared('config', static::$config);
    $this->initEventsManager($di);
    parent::setDI($di);
    Di::setDefault($di);
  }
  /**
   * @return \Phalcon\Registry
   */
  public function getUserOptions() {
    return $this->userOptions;
  }
  public function getUserOption($item, $default=null) {
    $useroption = isset($this->userOptions[$item])
        ? $this->userOptions[$item]
        : null;
    if (!$useroption) {
      $useroption = static::$config->get($item);
    }
    return $useroption ?: $default;
  }
  public function setUserOptions(array $options) {
    foreach($options as $item=>$value) {
      $this->setUserOption($item, $value);
    }
    return $this;
  }
  public function setUserOption($item, $value) {
    $this->userOptions[$item] = $value;
    return $this;
  }
  public static function getConfig() {
    return static::$config;
  }
  
  
  protected function initOptions(array $userOptions=null) {
    $this->userOptions = new Registry();
    $this->setUserOptions($this->_defaultOptions);
    if (is_array($userOptions)) {
      $this->setUserOptions($userOptions);
    }
  }
  
  protected function initPaths() {
    $paths = new Paths($this->getUserOptions());
  }

  public function loadEnv($file=null) {
    if (is_null($file)) {
      $file = $this->getUserOption('confdir').'/.env';
    }
    if (file_exists($file) && class_exists('Dotenv\Dotenv')) {
      $dotenv = new \Dotenv\Dotenv(dirname($file),basename($file));
      $dotenv->load();
    }
    if (!getenv('APP_ENV')) {
      putenv("APP_ENV=".static::ENV_PRODUCTION);
    }
    switch (getenv('APP_ENV')) {
      case static::ENV_TESTING :
      case static::ENV_DEVELOPMENT : {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        if (extension_loaded('xdebug'))
          ini_set('xdebug.collect_params', 4);
        break;
      }
      case static::ENV_STAGING :
      case static::ENV_PRODUCTION : {
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(0);
        if (!headers_sent())
          header_remove('X-Powered-By');
      }
    }
  }
  
  /**
   * @param array|Config $confdir
   */
  protected function initConfig($config) {
    $confdir = $this->getUserOption('confdir');
    
    if ($config instanceof Config) {
      static::$config = $config;
    }
    else {
      static::$config = new Config(is_array($config)?$config:[]);
    }
    
    $this->mergeConfigFile(static::$config, $confdir.'/config.php');
    $this->mergeConfigFile(static::$config, $confdir.'/'.getenv('APP_ENV').'.php');

    return $this;
  }
  public function appendConfig($config) {
    if (is_array($config)) {
      $config = new Config($config);
    }
    if ($config instanceof Config) {
      static::$config->merge($config);
    }
  }
  protected function mergeConfigFile(Config &$config, $file) {
    if (is_readable($file)) {
      $mergeconf = include $file;
      
      if (is_array($mergeconf))
        $mergeconf = new Config($mergeconf);
      
      if ($mergeconf instanceof Config)
        $config->merge($mergeconf);
    }
  }
  

  
  
  public function run(Di $di=null) {
    $di     = $this->initDi($di);
    $config = static::$config;
    
    $this->fireEvent('beforeRun');
    
    $this->initLoader();
    $this->initApplication();
    if ($this->_shouldAutoloadModules()) {
      $this->initModules($this->_moduleConfig(), $this->_moduleOptions());
    }
    $this->fireEvent('afterRun');
    return $this;
  }
  
  protected function initDi(Di $di=null) {
    if ($di) {
      $this->setDI($di);
    }
    if (!$this->getDI()) {
      $this->setDI(new FactoryDefault());
    }
    return $this->getDI();
  }
  protected function initLoader() {
    $autoload = static::$config->get('autoload');
    if ($autoload) {
      $loader = new Loader();
      
      if ($autoload->get('dir') instanceof Config)
        $loader->registerDirs($autoload->get('dir')->toArray());
      
      if ($autoload->get('namespace') instanceof Config)
        $loader->registerNamespaces($autoload->get('namespace')->toArray());
      
      $loader->register();
    }
  }
  protected function initApplication() {
    $di = $this->getDI();
    if (!$this->getUserOption('app') instanceof Application) {
      $this->setUserOption('app', new Application);
    }
    $this->app = $this->getUserOption('app');
    $this->app->setDI($di);
    $this->app->setEventsManager($di->get('eventsManager'));
    
    // disable implicit views if using simple views
    if ($di->has('view') && !$di->get('view') instanceof ViewInterface) {
      $this->app->useImplicitView(false);
    }
    $di->setShared('app', $this->app);
  
    if (!defined('APP_ENV')) {
      define('APP_ENV', getenv('APP_ENV') ?: static::ENV_PRODUCTION);
    }
  }
  
  /**
   * @return \Phalcon\Mvc\Application
   */
  public function getApp() {
    return $this->app?:$this->run()->app;
  }
  public function getContent($uri = null) {
    static $cache = array();
    $key = $uri?:0;
    if (!isset($cache[$key]))
      $cache[$key] = $this->getApp()->handle($uri)->getcontent();
    return $cache[$key];
  }
  

  protected function initEventsManager(Di $di) {
    if (!$di->has('eventsManager') || $di->getService('eventsManager')->getDefinition()=='Phalcon\Events\Manager') {
      $di->setShared('eventsManager', $this->getEventsManager());
    }
    $this->setEventsManager($di->get('eventsManager'));
  }

  /**
   * Returns the internal event manager
   *
   * @return \Phalcon\Events\ManagerInterface 
   */
  public function getEventsManager() {
    if (!is_object(parent::getEventsManager())) {
      $em = $this->getUserOption('eventsManager');
      if (!is_object($em)) {
        $em = new EventsManager();
      }
      $em->enablePriorities(true);
      $this->setEventsManager($em);
    }
    return parent::getEventsManager();
  }
  
  

  protected function _shouldAutoloadModules() {
    $modconfset = !empty($this->_moduleConfig()->toArray());
    $defaultmodset = !empty($this->_moduleOptions()['defaultModule']);
    return !$this->modules && ($modconfset || $defaultmodset);
  }
  /**
   * @return \Phalcon\Config
   */
  protected function _moduleConfig() {
    $modconf = $this->getUserOption('modules');
    if (!$modconf instanceof Config)
      $modconf = new Config(is_array($modconf)?$modconf:[]);
    return $modconf;
  }
  protected function _moduleOptions() {
    return [
        'defaultModule' => $this->getUserOption('defaultModule'),
    ];
  }
  public function initModules($modconf=null, $options=null) {
    $this->modules = new Modules(
        $this->getDi(),
        $this->getApp(),
        $modconf,
        $options
    );
  }
  
  /**
   * Attach an event listener, if no event manager has been setup it will set one up for you.
   * @param string $name leave blank (null, false, '') to place a listener for all events or specify the event you want
   * @param object|callable $handler
   */
  public function attachEventListener($name, $handler) {
    $em    = $this->getEventsManager();
    if (!$name) {
      $event = self::EVENT_PREFIX;
    }
    elseif (!strstr($name,':') && $name != self::EVENT_PREFIX) {
      $event = self::EVENT_PREFIX.':'.$name;
    }
    else {
      $event = $name;
    }
    
    $em->attach($event, $handler);
  }
  protected function fireEvent($name, $data=null, $cancelable=true) {
    $em = $this->getEventsManager();
    $event = self::EVENT_PREFIX.':'.$name;
    return $this->getEventsManager()->fire($event, $this, $data, $cancelable);
  }
}