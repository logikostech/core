<?php

namespace Logikos;


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

class Bootstrap {
  use \Logikos\UserOptionTrait;
  
  private $_defaultOptions = [
      'basedir'  => null,
      'confdir'  => null,
      'appdir'   => null,
      'config'   => null,
      'app'      => null,
      'eventman' => null,
      'env'      => 'development'
  ];
  
  /**
   * @var Application
   */
  public $app;
  
  /**
   * @var Config
   */
  public $config;
  
  const ENV_PRODUCTION  = 'production';
  const ENV_STAGING     = 'staging';
  const ENV_DEVELOPMENT = 'development';
  const ENV_TESTING     = 'testing';
  
  public function __construct(Di $di=null,array $userOptions = null) {
    $this->setDi($di);
    $this->initOptions($di,$userOptions);
    $this->initEventsManager($di);
    $config = $this->initConfig($di);
    
    //$this->initLoader($config);
    
    $this->initApplication($di, $config);
  }
  protected function setDi($di) {
    if (!$di instanceof Di)
      $di = \Phalcon\Di::getDefault() ?: new FactoryDefault();
    
    $this->_di = $di;
    \Phalcon\Di::setDefault($di);
  }
  public function getDi() {
    return $this->_di;
  }
  protected function initOptions(Di $di,$userOptions) {
    $this->_setDefaultUserOptions($this->_defaultOptions);
    if (is_array($userOptions))
      $this->mergeUserOptions($userOptions);
    $this->checkBasedir();
    $this->checkAppdir();
    $this->checkConfdir();
    $this->loadEnv();
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
    $dir = $this->getUserOption($option) ?: $default;
    
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
    $em = $this->getUserOption('eventman');
    
    if (!$em instanceof EventsManager)
      $em = new EventsManager();
    
    $em->enablePriorities(true);
    $di->setShared('eventsManager', $em);
  }
  
  protected function loadEnv() {
    if (file_exists($this->getUserOption('confdir').'/.env')) {
      $dotenv = new \Dotenv\Dotenv($this->getUserOption('confdir'));
      $dotenv->load();
    }
    
    if (!defined('APP_ENV'))
      define('APP_ENV', getenv('APP_ENV') ?: static::ENV_PRODUCTION);
    
    switch (APP_ENV) {
      case static::ENV_DEVELOPMENT : {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        if (extension_loaded('xdebug'))
          ini_set('xdebug.collect_params', 4);
        break;
      }
      case static::ENV_PRODUCTION : {
        if (!headers_sent())
          header_remove('X-Powered-By');
        error_reporting(E_ALL ^ E_NOTICE);
      }
    }
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
    $this->mergeConfigFile($config, $confdir.'/'.APP_ENV.'.php');
    
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
  
  protected function initApplication(Di $di, Config $config) {
    $app = $this->getUserOption('app');
    if (!$app instanceof Application)
      $app = new Application;
    $this->app = $app;
    $di->setShared('app', $app);
  }
}