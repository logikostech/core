<?php
namespace Logikos\Application\Bootstrap;

use Phalcon\Registry;

class Paths {
  
  /**
   * @var Registry
   */
  protected $userOptions;
  
  public function __construct(Registry $userOptions) {
    $this->userOptions = $userOptions;
    $this->initPaths();
  }
  public function getUserOptions() {
    return $this->userOptions;
  }
  public function getUserOption($item, $default=null) {
    return isset($this->userOptions[$item]) && $this->userOptions[$item]
        ? $this->userOptions[$item]
        : $default;
  }
  protected function setUserOption($item, $value) {
    $this->userOptions[$item] = $value;
  }

  protected function initPaths() {
    $this->checkBasedir();
    $this->_checkDir('appdir',$this->findAppDir(),'APP_DIR');
    $this->_checkDir('confdir',$this->findConfDir(),'CONF_DIR');
    $this->_checkDir('pubdir',$this->findPubDir(),'PUB_DIR');
  }
  

  protected function checkBasedir() {
    $dir = $this->getUserOption('basedir', __DIR__);
    $default = substr(__FILE__,0,strrpos($dir,'/vendor/'));
    $this->_checkDir('basedir',$default,'BASE_DIR');
  }
  private function _checkDir($option,$default,$constant=null) {
    $dir = $this->getUserOption($option,$default);
    if ($dir && is_dir($dir)) {
      $dir = realpath($dir);
      $this->setUserOption($option,$dir);
      if ($constant && !defined($constant)) {
        define($constant,$dir.'/');
        putenv("{$constant}={$dir}/");
      }
    }
  }
  
  protected function findAppDir() {
    return $this->_findDir([
        $this->getUserOption('basedir') . '/app',
        $this->getUserOption('basedir') . '/apps'
    ]);
  }
  protected function findConfDir() {
    return $this->_findDir([
        $this->getUserOption('basedir') . '/config',
        $this->getUserOption('appdir')  . '/config'
    ]);
  }
  protected function findPubDir() {
    return $this->getUserOption('basedir') . '/public';
  }
  private function _findDir($check) {
    foreach($check as $dir) {
      if (is_dir($dir)) {
        return realpath($dir);
      }
    }
  }
}