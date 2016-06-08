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

class Bootstrap {
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
  
  const ENV_PRODUCTION  = 'production';
  const ENV_STAGING     = 'staging';
  const ENV_DEVELOPMENT = 'development';
  const ENV_TESTING     = 'testing';
  
  public function __construct(Di $di=null,array $userOptions = null) {
    $this->setDi($di);
    $this->initOptions($di,$userOptions);
    $this->initEventsManager($di);
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
    $this->initModules();
    
    $di->setShared('app', $this->app);
    return $this;
  }

  /**
   * @return \Phalcon\Mvc\Application
   */
  public function getApp() {
    return $this->app?:$this->run()->app;
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
  
  
  protected function initModules() {
    
    $detected = $this->_detectModuleConf();
    $fromconf = $this->config->get('modules',[]);
    $modconf  = $this->_mergeModConf($detected,$fromconf);
    $modarray = $modconf->toArray();
    
    if (count($modarray)) {
      $this->app->registerModules($modarray);
      $this->autosetDefaultModule(array_keys($modarray));
      $this->autoRouteModules();
    }
  }
  protected function autosetDefaultModule(array $mods) {
    $default = $this->config->get(
        'defaultModule',
        $this->getUserOption('defaultModule')
    );
    if (!$default && in_array('frontend',$mods))
      $default = 'frontend';
    
    if ($default)
      $this->setDefaultModule($default);
  }
  
  public function setDefaultModule($default) {
    $this->app->setDefaultModule($default);
    $this->getDi()->get('router')->setDefaultModule($default);
  }
  private function _detectModuleConf() {
    $mods     = $this->_getPotentialModuleFiles();
    $classes  = $this->_getPotentialModuleClasses();
    $modconf  = [];
    foreach($mods as $modname => $modfile) {
      $search = $modname.'\Module';
      foreach($classes as $i=>$fqcn) {
        $findin = substr($fqcn,-1*strlen($search));
        if (strtolower($findin)==strtolower($search)) {
          $modconf[$modname] = ['className' => $fqcn,'path' => $modfile];
          break;
        }
      }
    }
    return $modconf;
  }
  private function _getPotentialModuleFiles() {
    // loop though potential module dirs
    $dirs = glob(APP_DIR.'modules/*',GLOB_ONLYDIR);
    $mods = [];
    foreach($dirs as $dir) {
      $dir = realpath($dir);
      $modname = basename($dir);
      $modfile = $dir.'/Module.php';
      if (file_exists($modfile)) {
        require_once $modfile;
        $mods[$modname] = $modfile;
      }
    }
    return $mods;
  }
  private function _getPotentialModuleClasses() {
    // array of potential module classes
    $classes = array_filter(get_declared_classes(),function($name){
      if (substr(strtolower($name),-7)=='\module') {
        $rc = new \ReflectionClass($name);
        return $rc->implementsInterface('Phalcon\Mvc\ModuleDefinitionInterface');
      }
      return false;
    });
    return $classes;
  }
  private function _mergeModConf($conf,$merge) {
    if (!$conf instanceof Config) {
      if (!is_array($conf))
        $conf = array();
      $conf = new Config($conf);
    }
    if (!$merge instanceof Config) {
      if (!is_array($merge))
        $merge = array();
      $merge = new Config($merge);
    }
    return $conf->merge($merge);
  }
  
  
  protected function autoRouteModules() {
    $default = $this->app->getDefaultModule();
    $mods = $this->app->getModules();
    if (count($mods)) {
      // note, it is important that the default module is registered first..
      // though if user uses Phalcon\Mvc\Router these default routes are not needed
      if (isset($mods[$default]))
        $this->routeModule($default, true);
      
      foreach ($mods as $key => $module)
        if ($key != $default)
          $this->routeModule($key, false);
    }
  }
  protected function routeModule($key,$default=false) {
    $router  = $this->getDi()->get('router');
    $mod = $default ? '' : "/{$key}";
    $router->add("{$mod}/:params", array(
        'module' => $key,
        'controller' => 'index',
        'action' => 'index',
        'params' => 1
    ))->setName($key);
    $router->add("{$mod}/:controller/:params", array(
        'module' => $key,
        'controller' => 1,
        'action' => 'index',
        'params' => 2
    ));
    $router->add("{$mod}/:controller/:action/:params", array(
        'module' => $key,
        'controller' => 1,
        'action' => 2,
        'params' => 3
    ));
  }
  
}