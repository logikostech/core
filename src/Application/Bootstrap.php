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

class Bootstrap extends Injectable {
  use \Logikos\UserOptionTrait;
  
  private $_defaultOptions = [
      'basedir'  => null,
      'confdir'  => null,
      'appdir'   => null,
      'config'   => null,
      'app'      => null,
      'eventsManager' => null,
      'env'      => 'development',
      'defaultModule' => 'frontend'
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
  
  const ENV_PRODUCTION  = 'production';
  const ENV_STAGING     = 'staging';
  const ENV_DEVELOPMENT = 'development';
  const ENV_TESTING     = 'testing';
  
  public function __construct(Di $di=null, array $userOptions = null) {
    $this->setDi($di);
    $this->initOptions($di,$userOptions);
    $this->initEventsManager($di);
    Di::setDefault($di);
  }
  
  public function run() {
    if (!defined('APP_ENV'))
      define('APP_ENV', getenv('APP_ENV') ?: static::ENV_PRODUCTION);
    
    $di     = $this->getDi();
    $config = $this->initConfig($di);
    
    if ($config->get('autoload'))
      $this->initLoader($config->get('autoload'));
    
    $this->app = $this->getUserOption('app',new Application);
    $this->app->setDI($di);
    $this->app->setEventsManager($di->get('eventsManager'));
    if ($this->_shouldAutoloadModules())
      $this->initModules($this->_moduleConfig(), $this->_moduleOptions());
    
    $di->setShared('app', $this->app);
    return $this;
  }

  /**
   * @return \Phalcon\Mvc\Application
   */
  public function getApp() {
    return $this->app?:$this->run()->app;
  }
  
  
  protected function initOptions(Di $di,$userOptions) {
    $this->_setDefaultUserOptions($this->_defaultOptions);
    if (is_array($userOptions))
      $this->mergeUserOptions($userOptions);
    $this->checkBasedir();
    $this->checkAppdir();
    $this->checkConfdir();
    $this->loadEnv();
    $this->checkUserApp();
  }

  protected function checkBasedir() {
    $dir = $this->getUserOption('basedir');
    $default = substr(__FILE__,0,strrpos($dir,'/vendor/'));
   
    $this->_checkDir('basedir',$default,'BASE_DIR');
  }
  protected function checkAppdir() {
    $this->_checkDir('appdir',$this->findAppdir(),'APP_DIR');
  }
  protected function checkConfdir() {
    $this->_checkDir('confdir',$this->findConfdir(),'CONF_DIR');
  }
  private function _checkDir($option,$default,$constant=null) {
    $dir = $this->getUserOption($option,$default);
    if ($dir && is_dir($dir)) {
      $dir = realpath($dir);
      $this->setUserOption($option,$dir);    
      if ($constant && !defined($constant))
        define($constant,$dir.'/');
    }
  }
  
  protected function findAppDir() {
    return $this->_findDir([
        $this->getUserOption('basedir') . '/app',
        $this->getUserOption('basedir') . '/apps'
    ]);
  }
  protected function findConfdir() {
    return $this->_findDir([
        $this->getUserOption('basedir') . '/config',
        $this->getUserOption('appdir')  . '/config'
    ]);
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
  }
  
  public function loadEnv($file=null) {
    if (is_null($file))
      $file = $this->getUserOption('confdir').'/.env';
    
    if (file_exists($file)) {
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
  
  protected function checkUserApp() {
    if (is_object($this->getUserOption('app')))
      $this->app = $this->getUserOption('app');
  }
  
  /**
   * @param string $confdir
   * @return \Phalcon\Config
   */
  protected function initConfig(Di $di) {
    $config  = $this->getUserOption('config');
    $confdir = $this->getUserOption('confdir');
    
    if (!$config instanceof Config)
      $config = new Config(is_array($config)?$config:[]);
    
    $this->mergeConfigFile($config, $confdir.'/config.php');
    $this->mergeConfigFile($config, $confdir.'/'.getenv('APP_ENV').'.php');
    
    $this->config = $config;
    $di->setShared('config', $config);
    return $config;
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
    return $modconfset || $defaultmodset;
  }
  /**
   * @return \Phalcon\Config
   */
  protected function _moduleConfig() {
    return $this->config->get('modules',new Config([]));
  }
  protected function _moduleOptions() {
    return [
        'defaultModule' => $this->config->get('defaultModule',$this->getUserOption('defaultModule')),
    ];
  }
  public function initModules($modconf, $options=null) {
    $this->modules = new Modules(
        $this->getDi(),
        $this->getApp(),
        $modconf,
        $options
    );
  }
  
}