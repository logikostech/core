<?php

namespace Logikos\Tests;

use Logikos\Bootstrap;
use Phalcon\Di;

class BootstrapTest extends \PHPUnit_Framework_TestCase {
  static $di;

  public static function setUpBeforeClass() {
    require_once substr(__DIR__.'/',0,strrpos(__DIR__.'/','/tests/')+7).'bootstrap.php';
    static::$di = \Phalcon\Di::getDefault();
  }
  public function setUp() {
    static::$di = new Di();
    Di::setDefault(static::$di);
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
  
  public function testAutoloadFromdir() {
    $b = $this->getBootstrap([
        'config' => [
            'autoload' => [
                'dir' => [
                    APP_DIR.'/library/'
                ]
            ]
        ]
    ]);
    # Example of what the above config should do...  
//     (new \Phalcon\Loader)->registerDirs([
//         APP_DIR.'/library/'
//     ])->register();
    $this->assertCanLoadClass('LogikosTest\A\B');
  }
  public function testAutoloadByNamespace() {
    $b = $this->getBootstrap([
        'config' => [
            'autoload' => [
                'namespace' => [
                    'LTest' => APP_DIR.'/library/'
                ]
            ]
        ]
    ]);
    # Example of what the above config should do...  
//     (new \Phalcon\Loader)->registerNamespaces([
//         'LTest' => APP_DIR.'/library/'
//     ])->register();
    $this->assertCanLoadClass('LTest\Foo\Bar');
  }
  public function testModuleRouting() {
    $b = $this->getBootstrap([
        'config' => [
            'modules' => [
                'frontend' => [
                    'className' => 'LT\Frontend\Module',
                    'path'      => APP_DIR.'/module/frontend'
                ]
            ]
        ]
    ]);
  }
  
  
  protected function assertCanLoadClass($className) {
    $this->assertTrue(class_exists($className),"Failed to load class '{$className}'");
  }
  protected function getBootstrap($options=[]) {
    return new Bootstrap(
        static::$di,
        array_merge(
            ['basedir'=>__DIR__],
            $options
        )
    );
  }
  
  
}