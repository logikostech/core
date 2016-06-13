<?php

namespace Logikos\Application\Bootstrap;

use Phalcon\Di;
use Phalcon\Config;
use Phalcon\Loader;
use Phalcon\Mvc\Application;
use Phalcon\Di\Injectable;

class Modules extends Injectable {
  
  /**
   * @var Application
   */
  public $app;
   
  /**
   * @var Config
   */
  public $config;

  private $_options = [
      'defaultModule' => 'frontend'
  ];
  
  public function __construct(Di $di, Application $app, $config=[], array $userOptions = null) {

    $this->setDi($di);
    if (!$config instanceof Config)
      $config = new Config(is_array($config)?$config:[]);
  
    $this->app    = $app;
    $this->initOptions($di,$userOptions);
    $default  = $this->getDefaultModule();
    $detected = $this->_detectModuleConf();
    $modconf  = $this->_mergeModConf($detected,$config);
    $modarray = $modconf->toArray();
    
    $this->config = $modconf;
    
    if (count($modarray)) {
      $app->registerModules($modarray);
      if (in_array($default,array_keys($modarray))) {
        $app->setDefaultModule($default);
        $this->router->setDefaultModule($default);
      }
      $this->initModuleRouting($app);
    }
  }
  /**
   * @return \Phalcon\Mvc\Application
   */
  public function getApp() {
    return $this->app;
  }
  public function modlist() {
    return array_keys($this->getApp()->getModules());
  }
  public function getDefaultModule() {
    return $this->app->getDefaultModule() ?: $this->getUserOption('defaultModule');
  }
  public function isDefaultModule($moduleName) {
    return $moduleName == $this->getDefaultModule();
  }
  public function getModulesDir() {
    $dir = $this->getUserOption('modulesDir');
    
    if (!$dir && defined('APP_DIR') && is_dir(APP_DIR.'modules'))
      $dir = APP_DIR.'modules';
    
    if ($dir && !is_dir($dir))
      throw new Exception("modulesDir does not exist: '{$dir}'");
    
    return rtrim($dir,'/').'/';
  }
  public function getUserOption($option, $default=null) {
    return isset($this->_options[$option]) ? $this->_options[$option] : $default;
  }
  public function getUserOptions() {
    return $this->_options;
  }
  protected function initOptions(Di $di,$userOptions) {
    if (is_array($userOptions))
      $this->_options = array_merge($this->_options,$userOptions);
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
    $dirs = glob($this->getModulesDir().'*',GLOB_ONLYDIR);
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
  
  public function getModules() {
    return $this->getApp()->getModules() ?: [];
  }
  public function isModuleDefined($moduleName) {
    $modules = $this->getApp()->getModules();
    return isset($modules[$moduleName]);
  }

  protected function initModuleRouting(Application $app) {
    // note, it is important that the default module is registered first..
    // though if user uses Phalcon\Mvc\Router these default routes are not needed
    if ($this->isModuleDefined($app->getDefaultModule()))
      $this->routeModule($app->getDefaultModule());
    
    foreach ($this->getModules() as $moduleName => $module)
      if (!$this->isDefaultModule($moduleName))
        $this->routeModule($moduleName);
  }
  protected function routeModule($moduleName) {
    if (isset($this->config[$moduleName]->className)) {
      $className = $this->config[$moduleName]->className;
      if (method_exists($className,'defineRoutes')) {
        $className::defineRoutes($this->getDI());
        return;
      }
    }
    $this->autoRouteModule($moduleName);
  }
  protected function autoRouteModule($moduleName) {
    $uriprefix = $this->isDefaultModule($moduleName)
      ? ''
      : "/{$moduleName}";
    
    $this->router->add("{$uriprefix}/:params", array(
        'module' => $moduleName,
        'controller' => 'index',
        'action' => 'index',
        'params' => 1
    ))->setName($moduleName);
    $this->router->add("{$uriprefix}/:controller/:params", array(
        'module' => $moduleName,
        'controller' => 1,
        'action' => 'index',
        'params' => 2
    ));
    $this->router->add("{$uriprefix}/:controller/:action/:params", array(
        'module' => $moduleName,
        'controller' => 1,
        'action' => 2,
        'params' => 3
    ));
  }
}