<?php

use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\Yaml\Yaml;

class App
{
  static protected $_instances = array();
  static protected $_filename = null;
  
  static public $datesMap = array();
  
  public function __construct() 
  {
    // Set paths
    require_once __DIR__ . '/Symfony/Component/ClassLoader/UniversalClassLoader.php';
    
    $loader = new UniversalClassLoader();
    
    $loader->registerNamespaces(array(
        'Symfony' => __DIR__,
    ));
    
    $loader->registerPrefixes(array(
        'App_'    => __DIR__,
    ));
    
    $loader->register();
    
    require_once __DIR__ . '/Swift/lib/swift_required.php';
    
    // Set configuration
    foreach(array('app', 'blogs', 'calendars') as $cnf) {
      $config = Yaml::parse(__DIR__ . "/../conf/$cnf.yml");
      App_Config::setConfig($config);
    }
    
    // Initialize output file
    $filename = App_Config::get('app.output.file');
    
    if (!file_exists($filename)) {
      touch($filename);
    }
    
    file_put_contents($filename, "");
    self::$_filename = $filename;
  }
  
  static public function __callStatic($name, $arguments) 
  {
    $class = "App_" . ucfirst($name);
    
    if (empty(self::$_instances[$class])) {
      self::$_instances[$class] = new $class();
    }
    
    return self::$_instances[$class];
  }
  
  static public function conf($name)
  {
    return App_Config::get($name);
  }
  
  static public function output($str = null)
  {
    if ($str === null) {
      return file_get_contents(self::$_filename);
    } else {
      return file_put_contents(self::$_filename, $str, FILE_APPEND | LOCK_EX);
    }
  }
  
  static public function mapDate($date)
  {
    $date = is_a($date, 'DateTime') ? $date : new DateTime($date);
    $hash = date_format($date, 'Y-m-d');
    
    if (!isset(self::$datesMap[$hash])) {
      self::$datesMap[$hash] = self::view()->renderDate($date, true, false, true);
    }
  }
}