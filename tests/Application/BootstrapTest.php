<?php

namespace Logikos\Tests\Application;

use Logikos\Application\Bootstrap;
use Phalcon\Di\FactoryDefault as Di;
use Phalcon\Di\Injectable;
use Phalcon\Mvc\User\Plugin;

class BootstrapTest extends \PHPUnit_Framework_TestCase {
  static $di;
  static $basedir;
  
  const IS_DEFAULT_MODULE = 1;
  const NOT_DEFAULT_MODULE = 0;
  
  public static function setUpBeforeClass() {
    static::$basedir = realpath(substr(__DIR__.'/',0,strrpos(__DIR__.'/','/tests/')+7));
    require_once static::$basedir.'/_bootstrap.php';
  }
  public function setUp() {
    static::$di = new Di();
    Di::setDefault(static::$di);
  }
  public function testConstantsAreSet() {
    $b = $this->getBootstrap()->run();
    $this->assertTrue(defined('BASE_DIR'),'BASE_DIR not defined');
    $this->assertTrue(defined('APP_DIR'),'APP_DIR not defined');
    $this->assertTrue(defined('CONF_DIR'),'CONF_DIR not defined');
  }
  public function testConfigIsInDi() {
    $b = new Bootstrap(static::$di);
    $b->run();
    $this->assertInstanceOf('Phalcon\\Config', static::$di->get('config'));
  }
  public function testEnvLoaded() {
    $b = $this->getBootstrap();
    $this->assertEquals('development', getenv('APP_ENV'));
    $b->run();
    $this->assertTrue(defined('APP_ENV'));
  }
  public function testConfigFileLoaded() {
    $b = $this->getBootstrap()->run();
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
    $b->run();
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
  
  

  public function testDefaultModuleRouting() {
    $uri = 'foo/bar/arg1';
    $app = $this->getModuleBootstrap()->getApp();
    $this->assertRouteWorks($uri,self::IS_DEFAULT_MODULE);
  }
  public function testOtherModuleRouting() {
    $uri = 'backend/foo/bar/arg1';
    $app = $this->getModuleBootstrap()->getApp();
    $this->assertRouteWorks($uri,self::NOT_DEFAULT_MODULE);
  }
  public function testSetDefaultModuleViaConfig() {
    $config = [
        'modules' => $this->getModules(),
        'defaultModule' => 'backend'
    ];
    $app = (new Bootstrap(
        static::$di,
        [
            'basedir' => static::$basedir,
            'config' => $config
        ]
    ))->getApp();
    $this->assertEquals('backend',$app->getDefaultModule());
    $this->assertRouteWorks('foo/bar',self::IS_DEFAULT_MODULE);
  }
  public function testSetDefaultModuleViaUseroptions() {
    $config = [
        'modules' => $this->getModules()
    ];
    
    $app = (new Bootstrap(
        static::$di,
        [
            'basedir' => static::$basedir,
            'defaultModule' => 'backend',
            'config' => $config
        ]
    ))->getApp();
    $this->assertEquals('backend',$app->getDefaultModule());
    $this->assertRouteWorks('foo/bar',self::IS_DEFAULT_MODULE);
  }
  protected function assertRouteWorks($uri,$usesDefaultModule=false) {
    $uri    = '/'.trim($uri,'/');
    $uriarg = explode('/',trim($uri,'/'));
    $router = $this->di()->router;
    $router->handle($uri);
    $defaultModule = $this->di()->app->getDefaultModule();

    if ($usesDefaultModule == self::IS_DEFAULT_MODULE) {
      $this->assertEquals($defaultModule, $router->getModuleName());
      $this->assertEquals($uriarg[0], $router->getControllerName());
      $this->assertEquals($uriarg[1], $router->getActionName());
    }
    else {
      $this->assertEquals($uriarg[0], $router->getModuleName());
      $this->assertEquals($uriarg[1], $router->getControllerName());
      $this->assertEquals($uriarg[2], $router->getActionName());
    }
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
  protected function getBootstrap($options=[]) {
    return new Bootstrap(
        static::$di,
        array_merge(
            ['basedir'=>static::$basedir],
            $options
        )
    );
  }
  protected function getModuleBootstrap($modules=[],$default=null) {
    if (empty($modules))
      $modules = $this->getModules();
    
    $options = [
        'config' => [
            'modules' => $modules
        ]
    ];
    if ($default)
      $options['defaultModule'] = $default;
//      $options['config']['defaultModule'] = $default;
    
    return $this->getBootstrap($options);
  }
  

  private function getModules() {
    return [
        'frontend' => [
            'className' => 'LT\Frontend\Module',
            'path'      => APP_DIR.'modules/frontend'
        ],
        'backend' => [
            'className' => 'LT\Backend\Module',
            'path'      => APP_DIR.'modules/backend'
        ]
    ];
  }
  
}