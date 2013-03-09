<?php

include_once 'App/Config.php';
include_once 'App/Http.php';
include_once 'App/Log.php';
include_once 'App/Service.php';
include_once 'App/Utils.php';
include_once 'App/View.php';

class App 
{
  static protected $_instances = array();
  static protected $_filename = null;
  
  public function __construct($config) 
  {
    // Set configuration
    Config::setConfig($config);
    
    // Initialize output file
    $filename = $config['app.output.file'];
    
    if (!file_exists($filename)) {
      touch($filename);
    }
    
    file_put_contents($filename, "");
    self::$_filename = $filename;
  }
  
  static public function __callStatic($name, $arguments) 
  {
    $class = ucfirst($name);
    
    if (empty(self::$_instances[$class])) {
      self::$_instances[$class] = new $class();
    }
    
    return self::$_instances[$class];
  }
  
  static public function conf($name)
  {
    return Config::get($name);
  }
  
  static public function output($str = null)
  {
    if ($str === null) {
      return file_get_contents(self::$_filename);
    } else {
      return file_put_contents(self::$_filename, $str, FILE_APPEND | LOCK_EX);
    }
  }
}