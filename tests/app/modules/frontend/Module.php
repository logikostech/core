<?php

namespace LT\Frontend;

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
        'Lt\Frontend\Controllers' => __DIR__.'/controllers/',
        'Lt\Frontend\Models'      => __DIR__.'/models/'
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
			$dispatcher->setDefaultNamespace("Lt\Frontend\Controllers");
			return $dispatcher;
		};
  }
}