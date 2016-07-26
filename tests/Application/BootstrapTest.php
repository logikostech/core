<?php

namespace Logikos\Tests\Application;

use Logikos\Application\Bootstrap;
use Phalcon\Config;
use Phalcon\Di\FactoryDefault as Di;
use Phalcon\Di\Injectable;
use Phalcon\Mvc\User\Plugin;

class BootstrapTest extends \PHPUnit_Framework_TestCase {
  /**
   * @var \Phalcon\Di
   */
  static $di;
  static $basedir;
  static $confdir;
  static $appdir;
  
  const IS_DEFAULT_MODULE = 1;
  const NOT_DEFAULT_MODULE = 0;
  
  protected static $userOptions;
  
  public static function setUpBeforeClass() {
    static::$basedir = realpath(substr(__DIR__.'/',0,strrpos(__DIR__.'/','/tests/')+7));
    require_once static::$basedir.'/_bootstrap.php';
    static::$appdir  = static::$basedir.'/app';
    static::$confdir = static::$appdir.'/config';
    $b = new Bootstrap(self::getUserOptions());
  }
  public function setUp() {
    static::$di = new Di();
    Di::setDefault(static::$di);
    putenv('APP_ENV');unset($_ENV['APP_ENV'],$_SERVER['APP_ENV']);
  }
  protected static function getUserOptions() {
    if (!self::$userOptions) {
      self::$userOptions = [
          'basedir' => static::$basedir,
          'confdir' => static::$confdir
      ];
      file_put_contents(static::$confdir.'/.env','APP_ENV='.Bootstrap::ENV_TESTING);
    }
    return self::$userOptions;
  }
  
  protected function startTest() {
    Bootstrap::$testing = true;
  }
  public function testNewBootstrapNoOptions() {
    putenv("APP_ENV=".Bootstrap::ENV_TESTING);
    $b = new Bootstrap();
    $this->assertInstanceOf('Logikos\Application\Bootstrap', $b);
  }
  public function testConstantsAreSet() {
    $b = $this->getBootstrap();
    $b->run();
    $this->assertTrue(defined('BASE_DIR'),'BASE_DIR not defined');
    $this->assertTrue(defined('APP_DIR'),'APP_DIR not defined');
    $this->assertTrue(defined('CONF_DIR'),'CONF_DIR not defined');
  }
  public function testConfigIsAccessableAndInDi() {
    $b = new Bootstrap(self::getUserOptions());
    $b->setDI(static::$di);
    $this->assertInstanceOf('Phalcon\\Config', Bootstrap::getConfig());
    $this->assertInstanceOf('Phalcon\\Config', static::$di->get('config'));
  }
  public function testEnvLoaded() {
    $this->assertTrue(class_exists('Dotenv\Dotenv'),"test can not pass without Dotenv\Dotenv, please run composer update.");
    file_put_contents(static::$confdir.'/.env','APP_ENV='.Bootstrap::ENV_DEVELOPMENT);
    $b = $this->getBootstrap();

    $this->assertEquals(Bootstrap::ENV_DEVELOPMENT, getenv('APP_ENV'));
    $b->run();
    $this->assertTrue(defined('APP_ENV'));
  }
  
  public function testConfigFileLoaded() {
    $b = $this->getBootstrap()->run();
    $this->assertEquals('bar', static::$di->get('config')->foo);
  }
  
  public function testAutoloadFromdir() {
    $b = $this->getBootstrap();
    $b->appendConfig([
        'autoload' => [
            'dir' => [
                self::$appdir.'/library/'
            ]
        ]
    ]);
    $b->run();
    # Example of what the above config should do...  
//     (new \Phalcon\Loader)->registerDirs([
//         APP_DIR.'/library/'
//     ])->register();
    $this->assertCanLoadClass('LogikosTest\A\B');
  }
  
  public function testAutoloadByNamespace() {
    $b = $this->getBootstrap();
    $b->appendConfig([
        'autoload' => [
            'namespace' => [
                'LTest' => self::$appdir.'/library/'
            ]
        ]
    ]);
    $b->run();
    # Example of what the above config should do...  
//     (new \Phalcon\Loader)->registerNamespaces([
//         'LTest' => APP_DIR.'/library/'
//     ])->register();
    $this->assertCanLoadClass('LTest\Foo\Bar');
  }
  
  
  
  public function testUsePreconfiguredApplication() {
    $app = new \Phalcon\Mvc\Application;
    $app->foo = 'bar';
    $b = $this->getBootstrap(['app' => $app])->run();
    $this->assertEquals('bar',$b->app->foo);
  }
  
  public function testStartMvcApplication() {
    $b = $this->getBootstrap();
    $this->assertInstanceOf('Phalcon\Mvc\Application', $b->getApp());
  }
  
  public function testCanAttachEvents() {
    $b  = $this->getBootstrap();
    $b->attachEventListener('beforeRun', function($event, $ltboot){
      $ltboot->beforeRunEventFired=true;
    });
    $b->attachEventListener('afterRun', function($event, $ltboot){
      $ltboot->afterRunEventFired=true;
    });
    $b->run();
    $this->assertTrue(isset($b->beforeRunEventFired), "beforeRun event didn't fire");
    $this->assertTrue(isset($b->afterRunEventFired), "afterRun event didn't fire");
  }

  public function testMaintainConfigObjectReference() {
    $config = new Config([
        'basedir' => static::$basedir
    ]);
    $b = new Bootstrap(self::getUserOptions(),$config,static::$di);
    $b->getConfig()->foo='bar';
    $this->assertEquals('bar',$config->get('foo'));
  }
  
  /**
   * @return \Phalcon\Mvc\User\Plugin
   */
  protected function di() {
    static $cache;
    if (!$cache) {
      $cache = new Plugin;
      $cache->setDI(static::$di);
    }
    return $cache;
  }
  protected function assertCanLoadClass($className) {
    $this->assertTrue(class_exists($className),"Failed to load class '{$className}'");
  }
  protected function getBootstrap($options=[], $config=[], $di=null) {
    $b = new Bootstrap(
        array_merge(
            $this->getUserOptions(),
            $options
        ),
        $config
    );
    $b->setDI($di?:static::$di);
    return $b;
  }
  
}