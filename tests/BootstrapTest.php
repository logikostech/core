<?php

namespace Logikos\Tests;

use Logikos\Bootstrap;

class BootstrapTest extends \PHPUnit_Framework_TestCase {
  static $di;

  public static function setUpBeforeClass() {
    require_once substr(__DIR__.'/',0,strrpos(__DIR__.'/','/tests/')+7).'bootstrap.php';
    static::$di = \Phalcon\Di::getDefault();
  }

  public function testConstantsAreSet() {
    $b = $this->getBootstrap();
    $this->assertTrue(defined('BASE_DIR'));
    $this->assertTrue(defined('APP_DIR'));
    $this->assertTrue(defined('CONF_DIR'));
  }
  public function testConfigIsInDi() {
    $b = new Bootstrap(static::$di);
    $this->assertInstanceOf('Phalcon\\Config', static::$di->get('config'));
  }
  public function testEnvLoaded() {
    $b = $this->getBootstrap();
    $this->assertTrue(defined('APP_ENV'));
    $this->assertEquals('development', getenv('APP_ENV'));
  }
  public function testConfigFileLoaded() {
    $b = $this->getBootstrap();
    $this->assertEquals('bar', static::$di->get('config')->foo);
  }

  protected function getBootstrap() {
    return new Bootstrap(static::$di,[
        'basedir' => realpath(__DIR__)
    ]);
  }
}