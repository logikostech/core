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
    $this->config = $config;
    $this->initOptions($di,$userOptions);
    $default  = $this->getDefaultModule();
    $detected = $this->_detectModuleConf();
    $modconf  = $this->_mergeModConf($detected,$config);
    $modarray = $modconf->toArray();
    
    if (count($modarray)) {
      $app->registerModules($modarray);
      if (in_array($default,array_keys($modarray))) {
        $app->setDefaultModule($default);
        $this->router->setDefaultModule($default);
      }
      $this->autoRouteModules($app);
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
  public function getModulesDir() {
    $dir =$this->getUserOption('modulesDir');
    
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
  
  


  protected function autoRouteModules(Application $app) {
    $default = $app->getDefaultModule();
    $mods = $app->getModules();
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
    $mod = $default ? '' : "/{$key}";
    $this->router->add("{$mod}/:params", array(
        'module' => $key,
        'controller' => 'index',
        'action' => 'index',
        'params' => 1
    ))->setName($key);
    $this->router->add("{$mod}/:controller/:params", array(
        'module' => $key,
        'controller' => 1,
        'action' => 'index',
        'params' => 2
    ));
    $this->router->add("{$mod}/:controller/:action/:params", array(
        'module' => $key,
        'controller' => 1,
        'action' => 2,
        'params' => 3
    ));
  }
}