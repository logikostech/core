<?php

namespace Logikos\Tests\Application;

use Logikos\Application\Bootstrap;
use Logikos\Application\Bootstrap\Modules;
use Phalcon\Di\Injectable;
use Phalcon\Mvc\User\Plugin;
use Phalcon\Di\FactoryDefault as Di;

class ModulesTest extends \PHPUnit_Framework_TestCase {
  /**
   * @var \Phalcon\Di
   */
  public static $di;
  public static $basedir;
  public static $confdir;
  public static $appdir;
  public static $userOptions;
  
  const IS_DEFAULT_MODULE = 1;
  const NOT_DEFAULT_MODULE = 0;
  
  
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
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
  }
  /* */
  public function testBootstrap() {
    $b = $this->getModuleBootstrap();
    $this->assertInstanceOf('Logikos\Application\Bootstrap', $b);
  }
  public function testModulesConstructor() {
    $m = new Modules(
        self::$di,
        new \Phalcon\Mvc\Application
    );
    $this->assertInstanceOf('Logikos\Application\Bootstrap\Modules', $m);
  }
  public function testDefaultModuleRouting() {
    $uri  = 'foo/bar/arg1';
    $boot = $this->getModuleBootstrap();
    $boot->getApp();
    $this->assertRouteWorks($uri,self::IS_DEFAULT_MODULE);
  }
  public function testOtherModuleRouting() {
    $uri = 'backend/foo/bar/arg1';
    $app = $this->getModuleBootstrap()->getApp();
    $this->assertRouteWorks($uri,self::NOT_DEFAULT_MODULE);
  }
  public function testSetDefaultModule() {
    $app = $this->getBootstrap([
        'basedir' => static::$basedir,
        'modules' => $this->getModules(),
        'defaultModule' => 'backend'
    ])->getApp();
    $this->assertEquals('backend',$app->getDefaultModule());
    $this->assertRouteWorks('foo/bar',self::IS_DEFAULT_MODULE);
  }
  public function testNoConfigDefaults() {
    $b = $this->getBootstrap();
    $b->initModules();
    $this->assertEquals('frontend',$b->getApp()->getDefaultModule());
    $this->assertRouteWorks('foo/bar',self::IS_DEFAULT_MODULE);
  }
  public function testManualBootstrapInitModules() {
    $userOptions = [
        'defaultModule' => 'backend'
    ];
    $b = $this->getBootstrap();
    $b->initModules(
        $this->getModules(),
        $userOptions
    );
    $this->assertEquals('backend',$b->getApp()->getDefaultModule());
    $this->assertRouteWorks('foo/bar',self::IS_DEFAULT_MODULE);
  }

  public function testModuleWithCustomRoutes() {
    $b = $this->getBootstrap();
    $b->initModules();
    
    $this->assertRouteMap(
        '/customroute/foo/a/b/c',
        'customroute',
        'index',
        'foo',
        ['a','b','c']
    );
  }
  protected function assertRouteMap($uri,$module,$controller,$action,$args=null) {
    $uri    = '/'.trim($uri,'/');
    $router = $this->di()->router;
    $router->handle($uri);

    $this->assertEquals($module,     $router->getModuleName(),'route did not map to correct module');
    $this->assertEquals($controller, $router->getControllerName(),'route did not map to correct controller');
    $this->assertEquals($action,     $router->getActionName(),'route did not map to correct action');
    
    if (is_array($args)) {
      $params = $router->getParams();
      foreach($args as $k=>$v) {
        $param = isset($params[$k])?$params[$k]:null;
        $this->assertEquals($v,$param,"route failed to match argument {$k}");
      }
    }
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

  protected function getModuleBootstrap($modules=[],$default=null) {
    if (empty($modules))
      $modules = $this->getModules();
    
    $options = [
        'modules' => $modules
    ];
    if ($default)
      $options['defaultModule'] = $default;
    
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