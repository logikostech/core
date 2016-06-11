<?php

use Phalcon\Di\FactoryDefault as Di;

define('TESTS_DIR', dirname(__FILE__));

$composer_autoloader = TESTS_DIR . "/../vendor/autoload.php";

if (file_exists($composer_autoloader))
  include_once $composer_autoloader;

$autoload = [
    'Logikos' => realpath(__DIR__.'/../src').'/'
];

$loader = new \Phalcon\Loader;
$loader->registerNamespaces($autoload);
$loader->register();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);