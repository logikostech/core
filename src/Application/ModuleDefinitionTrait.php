<?php

namespace Logikos\Application;

use Phalcon\DiInterface;

Trait ModuleDefinitionTrait {
  
  public static function getModuleDir() {
    $filename = (new \ReflectionClass(static::class))->getFileName();
    return dirname($filename);
  }
  public static function getModuleName() {
    $name = basename(static::getModuleDir());
    return strtolower($name);
  }
  
  public static function getModuleNamespace() {
    $namespace = (new \ReflectionClass(static::class))->getNamespaceName();
    return $namespace;
  }
  
  public static function getControllerNamespace() {
    return static::getModuleNamespace().'\Controllers';
  }
  
  public static function isDefaultModule(DiInterface $di) {
    /* @var $router \Phalcon\Mvc\Router */
    $router  = $di->get('router');
    $default = $router->getDefaults()['module'];
    return $default == static::getModuleName();
  }
  
  public static function getRouteUriPrefix(DiInterface $di) {
    return static::isDefaultModule($di) ? '' : '/'.static::getModuleName();
  }
  
  /**
   * This method will be called by Logikos\Application\Bootstrap\Modules
   * so that each module can define its own routes.
   * 
   * Take care not to acidently intercept routes defined by other modules!
   * @param DiInterface $di
   */
  public static function defineRoutes(DiInterface $di) {
    /* @var $router \Phalcon\Mvc\Router */
    $router    = $di->get('router');
    $uriprefix = static::getRouteUriPrefix($di);
    
    // get a list of real controller names within this module
    // it will route to the controller if it exists,
    // else to the correct Action in indexController
    $ctrlmatch   = implode('|',static::getControllerList());
    
    $router->add("{$uriprefix}/:params",static::routeconf([
        'params' => 1
    ]));
    $router->add("{$uriprefix}/:action/:params",static::routeconf([
        'action' => 1,
        'params' => 2
    ]));
    $router->add("{$uriprefix}/({$ctrlmatch})/:params",static::routeconf([
        'controller' => 1,
        'params'     => 2
    ]));
    $router->add("{$uriprefix}/({$ctrlmatch})/:action/:params",static::routeconf([
        'controller' => 1,
        'action'     => 2,
        'params'     => 3
    ]));
  }

  public static function routeconf($conf) {
    $base = [
        'module'     => static::getModuleName(),
        'controller' => 'index',
        'action'     => 'index'
    ];
    return array_merge($base,$conf);
  }
  

  public static function getControllerList() {
    $ctrldir = static::getModuleDir().'/controllers';
    $files   = glob($ctrldir.'/*Controller.php');
    $list    = [];
    foreach($files as $file) {
      $ctrlname = substr(basename($file),0,-14);
      $list[] = strtolower($ctrlname);
    }
    return $list;
  }
}