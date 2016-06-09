<?php

namespace Logikos\Tests\Application;

use Logikos\Application\Bootstrap;
use Logikos\Application\Bootstrap\Modules;
use Phalcon\Di\Injectable;
use Phalcon\Mvc\User\Plugin;
use Phalcon\Di\FactoryDefault as Di;

class ModulesTest extends \PHPUnit_Framework_TestCase {
  static $di;
  static $basedir;
  static $appdir;
  
  const IS_DEFAULT_MODULE = 1;
  const NOT_DEFAULT_MODULE = 0;
  
  public static function setUpBeforeClass() {
    static::$basedir = realpath(substr(__DIR__.'/',0,strrpos(__DIR__.'/','/tests/')+7));
    require_once static::$basedir.'/_bootstrap.php';
    static::$appdir = self::$basedir.'/app';
  }
  public function setUp() {
    static::$di = new Di();
    Di::setDefault(static::$di);
  }
  
  public function testBootstrap() {
    $b = $this->getModuleBootstrap();
    $this->assertInstanceOf('Logikos\Application\Bootstrap', $b);
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

  public function testManualBootstrapInitModules() {
    $b = $this->getBootstrap();
    $b->initModules(
        $this->getModules(),
        [
            'defaultModule' => 'backend'
        ]
    );
    $this->assertEquals('backend',$b->getApp()->getDefaultModule());
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
            'path'      => self::$appdir.'modules/frontend'
        ],
        'backend' => [
            'className' => 'LT\Backend\Module',
            'path'      => self::$appdir.'modules/backend'
        ]
    ];
  }
  
}