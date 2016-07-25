# Core classes required for logikos applications
Core for LT projects.

## Logikos\Application\Bootstrap
### Basic usage
```php
  // public/index.php

  $basedir = realpath(__DIR__.'/..');
  $appdir  = $basedir.'/app';
  
  /**
   * Composer
   */
  $composer = $basedir . '/vendor/autoload.php';
  if (file_exists($composer))
    include_once $composer;


  $boot = new Bootstrap([
      'basedir' => $basedir,
      'appdir'  => $appdir,
      'confdir' => $appdir.'/config'
  ]);

  /**
   * get loaded config from Bootstrap, which will auto merge $confdir."/".getenv('APP_ENV').".php"
   */
  $config = Bootstrap::getConfig();

  /**
   * Include services
   */
  $di = require APP_PATH . '/config/services.php';

  echo $boot->getContent();
```
## Logikos\Application\Bootstrap\Modules
If you pass module configuration information to Bootstrap(), either the module definitions or the defaultModule then bootstrap will automaticly try to initialize the modules Bootstrap::initModules()

Of course you can always initModules manualy yourself:
```php
  $appdir = realpath(__DIR__.'/../app');
  $boot = new Bootstrap($options);
  $boot->initModules(
    [
      'frontend' => [
        'className' => 'Frontend\Module',
        'path'      => $appdir.'/modules/frontend/Module.php'
      ],
      'backend' => [
        'className' => 'Backend\Module',
        'path'      => $appdir.'/modules/backend/Module.php'
      ]
    ],
    [
      'defaultModule' => 'frontend',
      'modulesDir'    => $appdir.'/modules'
    ]
  );
  echo $boot->getContent();
```
Note that by specifying modulesDir we really would not have needed to define the modules as the class automaticly finds and registers all modules within the modulesDir.  Also the default modulesDir is APP_DIR.'/modules' and the default defaultModule is 'frontend' so really $boot->initModules() would work all by itself with no options if the defaults work for your application.

### Module Routeing
By default the Modules class will automaticly register your modles with the router.  It uses /controller/action/params for the default module and /modulename/controller/action/params for all non-default modules.  You can override this per module however within your ModuleDefinition class
```php
class Module implements ModuleDefinitionInterface {
  public static function defineRoutes(DiInterface $di) {
    /* @var $router \Phalcon\Mvc\Router */
    $router  = $di->get('router');
    $default = $router->getDefaults()['module'];

    $router->add("/customroute/foo/:params", array(
        'module' => 'somemodule,
        'controller' => 'index',
        'action' => 'foo',
        'params' => 1
    ))->setName('somemodule_foo');
  }
  
```