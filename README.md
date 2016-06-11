# Core classes required for logikos applications
Core for LT projects.

## Logikos\Application\Bootstrap
### Basic usage
```php
  // public/index.php

  $basedir = realpath(__DIR__.'/..');
  $appdir  = $basedir.'/app';
  
  /**
   * Read the default module configuration
   */
  $config   = include $appdir . "/config/config.php";
  
  /**
   * Composer
   */
  $composer = $basedir . '/vendor/autoload.php';
  if (file_exists($composer))
    include_once $composer;

  /**
   * Include services
   */
  $di = require APP_PATH . '/config/services.php';

  $boot = new Bootstrap($di,$config);
  echo $boot->getApp()->handle()->getContent();
```
