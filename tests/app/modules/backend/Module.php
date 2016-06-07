<?php

namespace LT\Backend;

use Phalcon\Mvc\ModuleDefinitionInterface;
use Phalcon\DiInterface;
use Phalcon\Loader;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Dispatcher;

class Module implements ModuleDefinitionInterface {
  public function registerAutoloaders(DiInterface $di = null) {
    $l = new Loader();
    $dir = __DIR__;
    $l->registerNamespaces([
        'Lt\Backend\Controllers' => __DIR__.'/controllers/',
        'Lt\Backend\Models'      => __DIR__.'/models/'
    ]);
    $l->register();
  }
  
  public function registerServices(DiInterface $di) {
//     $di->setShared('view', function(){
//       $view = new View;
//       //$view->disable();
//       return $view;
//     });
		$di['dispatcher'] = function() {
			$dispatcher = new Dispatcher();
			$dispatcher->setDefaultNamespace("Lt\Backend\Controllers");
			return $dispatcher;
		};
  }
}