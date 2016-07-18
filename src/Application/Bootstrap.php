<?php

namespace Logikos\Application;


use Phalcon\Di;
use Phalcon\Config;
use Phalcon\Loader;
use Phalcon\Mvc\Router;
use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Application;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Url as UrlResolver;
use Phalcon\Error\Handler as ErrorHandler;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Logger\Adapter\File as FileLogger;
use Phalcon\Mvc\Model\Manager as ModelsManager;
use Phalcon\Di\Injectable;
use Logikos\Application\Bootstrap\Modules;
use Phalcon\Mvc\ViewInterface;

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
  public $config;
  
  /**
   * @var Modules
   */
  public $modules;
  
  const EVENT_PREFIX    = 'ltboot';
  const ENV_PRODUCTION  = 'production';
  const ENV_STAGING     = 'staging';
  const ENV_DEVELOPMENT = 'development';
  const ENV_TESTING     = 'testing';
  
  public function __construct(Di $di=null, $config = null, array $userOptions = null) {
    $di = $this->initDi($di);
    $this->initOptions($di, $config, $userOptions);
    $this->initEventsManager($di);
  }
  protected function initDi($di) {
    if (!$di instanceof Di)
      $di = new FactoryDefault();
    $this->setDi($di);
    Di::setDefault($di);
    return $di;
  }
  public function setUserOption($option, $value) {
    $this->config[$option] = $value;
  }
  public function getUserOption($option, $default=null) {
    $value = $this->config->get($option);
    
    if ($value instanceof Config)
      $value = $value->toArray();
    
    if (is_null($value) && !is_null($default))
      $value = $default;
    
    return $value;
  }
  public function setUserOptions(array $options) {
    $this->config->merge($options instanceof Config?$options:new Config($options));
  }
  public function getUserOptions() {
    return $this->config->toArray();
  }
  public function run() {
    $this->fireEvent('beforeRun');
    if (!defined('APP_ENV'))
      define('APP_ENV', getenv('APP_ENV') ?: static::ENV_PRODUCTION);
    $di     = $this->getDi();
    $config = $this->initConfig($di);
    
    if ($config->get('autoload'))
      $this->initLoader($config->get('autoload'));
    $this->app = $this->getUserOption('app',new Application);
    $this->app->setDI($di);
    $this->app->setEventsManager($di->get('eventsManager'));
    
    // disable implicit views if using simple views
    if ($di->has('view') && !$di->get('view') instanceof ViewInterface) {
      $this->app->useImplicitView(false);
    }
    if ($this->_shouldAutoloadModules())
      $this->initModules($this->_moduleConfig(), $this->_moduleOptions());
    
    $di->setShared('app', $this->app);
    $this->fireEvent('afterRun');
    return $this;
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
  
  protected function initOptions(Di $di, $config, $userOptions) {
    if ($config instanceof Config) {
      $this->config = $config;
      $c = new Config($this->_defaultOptions);
      foreach($c as $option=>$value) {
        if (!isset($config->$option)) {
          $this->config->option = $value;
        }
      }
    }
    else {
      $this->config = new Config($this->_defaultOptions);
      if (is_array($config) && count($config)) {
        $this->config->merge(new Config($config));
      }
    }
    
    if (is_array($userOptions)) {
      $this->config->merge(new Config($userOptions));
    }
    $this->checkBasedir();
    $this->_checkDir('appdir',$this->findAppDir(),'APP_DIR');
    $this->_checkDir('confdir',$this->findConfDir(),'CONF_DIR');
    $this->_checkDir('pubdir',$this->findPubDir(),'PUB_DIR');
    $this->loadEnv();
  }

  /**
   * Used to set defaults, will not overwrite existing values
   * 
   * @param array $options
   */
  protected function _setDefaultUserOptions(array $options) {
    foreach ($options as $option=>$value) {
      if (!$this->getUserOption($option))
        $this->setUserOption($option,$value);
    }
    return $this;
  }

  protected function checkBasedir() {
    $dir = $this->getUserOption('basedir');
    $default = substr(__FILE__,0,strrpos($dir,'/vendor/'));
   
    $this->_checkDir('basedir',$default,'BASE_DIR');
  }
  private function _checkDir($option,$default,$constant=null) {
    $dir = $this->getUserOption($option,$default);
    if ($dir && is_dir($dir)) {
      $dir = realpath($dir);
      $this->setUserOption($option,$dir);
      if ($constant && !defined($constant)) {
        define($constant,$dir.'/');
        putenv("{$constant}={$dir}/");
      }
    }
  }
  
  protected function findAppDir() {
    return $this->_findDir([
        $this->getUserOption('basedir') . '/app',
        $this->getUserOption('basedir') . '/apps'
    ]);
  }
  protected function findConfDir() {
    return $this->_findDir([
        $this->getUserOption('basedir') . '/config',
        $this->getUserOption('appdir')  . '/config'
    ]);
  }
  protected function findPubDir() {
    return $this->getUserOption('basedir') . '/public';
  }
  private function _findDir($check) {
    foreach($check as $dir)
      if (is_dir($dir))
        return realpath($dir);
  }
  
  protected function initEventsManager(Di $di) {
    $em = $this->getUserOption('eventsManager');
    
    if (!$em instanceof EventsManager)
      $em = new EventsManager();
    
    $em->enablePriorities(true);
    $di->setShared('eventsManager', $em);
    $this->setEventsManager($em);
  }
  
  public function loadEnv($file=null) {
    if (is_null($file))
      $file = $this->getUserOption('confdir').'/.env';

    if (file_exists($file) && class_exists('Dotenv\Dotenv')) {
      $dotenv = new \Dotenv\Dotenv(dirname($file),basename($file));
      $dotenv->load();
    }
    
    if (!getenv('APP_ENV'))
      putenv("APP_ENV=".static::ENV_PRODUCTION);
    
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
   * @param string $confdir
   * @return \Phalcon\Config
   */
  protected function initConfig(Di $di) {
    $confdir = $this->getUserOption('confdir');
    
    if (!$this->config instanceof Config)
      $this->config = new Config();
    
    $this->mergeConfigFile($this->config, $confdir.'/config.php');
    $this->mergeConfigFile($this->config, $confdir.'/'.getenv('APP_ENV').'.php');
    
    $di->setShared('config', $this->config);
    return $this->config;
  }
  protected function mergeConfigFile(Config &$config,$file) {
    if (is_readable($file)) {
      $mergeconf = include $file;
      
      if (is_array($mergeconf))
        $mergeconf = new Config($mergeconf);
      
      if ($mergeconf instanceof Config)
        $config->merge($mergeconf);
    }
  }

  protected function initLoader(Config $autoload) {
    $loader = new Loader();
    
    if ($autoload->get('dir') instanceof Config)
      $loader->registerDirs($autoload->get('dir')->toArray());
    
    if ($autoload->get('namespace') instanceof Config)
      $loader->registerNamespaces($autoload->get('namespace')->toArray());
    
    $loader->register();
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
    $em    = $this->getEventsManager();
    $event = self::EVENT_PREFIX.':'.$name;
    return $em->fire($event, $this, $data, $cancelable);
  }
}