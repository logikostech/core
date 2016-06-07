<?php

namespace LT\Backend\Controllers;

use Phalcon\Mvc\Controller;

class FooController extends Controller {
  public function indexAction() {
    var_dump(__METHOD__);
    echo __METHOD__;
  }

  public function barAction() {
    echo __METHOD__;
  }
}