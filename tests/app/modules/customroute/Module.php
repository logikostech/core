<?php

namespace LT\CustomRoute;

use Phalcon\Mvc\ModuleDefinitionInterface;
use Phalcon\DiInterface;
use Phalcon\Loader;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Dispatcher;

class Module implements ModuleDefinitionInterface {
  public static $modname = 'customroute';
  public static $ctrlnamespace = 'LT\CustomRoute\Controllers';
  
  public static function defineRoutes(DiInterface $di) {
    /* @var $router \Phalcon\Mvc\Router */
    $router  = $di->get('router');
    $default = $router->getDefaults()['module'];

    $router->add("/customroute/foo/:params", array(
        'module' => self::$modname,
        'controller' => 'index',
        'action' => 'foo',
        'params' => 1
    ))->setName(self::$modname.'_foo');
  }
  public function registerAutoloaders(DiInterface $di = null) {
    $l = new Loader();
    $dir = __DIR__;
    $l->registerNamespaces([
        self::$ctrlnamespace => __DIR__.'/'
    ]);
    $l->register();
  }
  
  public function registerServices(DiInterface $di) {
	$di['dispatcher'] = function() {
		$dispatcher = new Dispatcher();
		$dispatcher->setDefaultNamespace(self::$ctrlnamespace);
		return $dispatcher;
	};
  }
}