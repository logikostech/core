<?php

namespace LT\Frontend\Controllers;

use Phalcon\Mvc\Controller;

class FooController extends Controller {
  public function indexAction() {
    echo __METHOD__;
  }

  public function barAction() {
    echo __METHOD__;
  }
}