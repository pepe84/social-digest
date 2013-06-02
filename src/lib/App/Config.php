<?php

use Symfony\Component\Yaml\Yaml;

class App_Config 
{
  static protected $_path = null;
  static protected $_config = array();
  
  /**
   * 
   * @param string $path
   */
  static public function init($path)
  {
    // Default folder?
    if (empty($path) || $path === 'default') {
      $path = self::getDefaultPath();
    }
    
    // Set configuration
    foreach(array('app', 'blogs', 'calendars') as $cnf) {
      $file = "$path/$cnf.yml";
      if (file_exists($file)) {
        $config = Yaml::parse($file);
        self::_setConfig($config);
      }
    }
    
    // Store path
    self::$_path = $path;
  }
  
  /**
   * 
   * @return string
   */
  static public function getPath()
  {
    return self::$_path;
  }
  
  /**
   * 
   * @return string
   */
  static public function getDefaultPath()
  {
    return __DIR__ . "/../../conf";
  }
  
  /**
   *
   * @param array $config Multi-dimensional array
   */
  static protected function _setConfig(array $config)
  {
    self::$_config = array_merge(self::$_config, $config);
  }
  
  /**
   * Retrieve an option (using dots separator for iterative search)
   * 
   * @param  string $key
   * @param  mixed  $default
   * @return mixed
   */
  static public function get($key, $default = null)
  {
    $keys = explode('.', $key);
    
    // Root search
    $k = array_shift($keys);
    $opt = isset(self::$_config[$k]) ? self::$_config[$k] : null;
    
    // Iterative search
    foreach ($keys as $k) {
      if (!isset($opt[$k])) {
        return $default;
      }                
      $opt = $opt[$k];
    }
    
    return $opt !== null ? $opt : $default;
  }  
}