<?php

namespace LT\CustomRoute\Controllers;

use Phalcon\Mvc\Controller;

class IndexController extends Controller {
  public function indexAction() {
    var_dump(__METHOD__);
    echo __METHOD__;
  }

  public function fooAction() {
    var_dump([
        'method' => __METHOD__,
        'args' => func_get_args()
    ]);
  }
}