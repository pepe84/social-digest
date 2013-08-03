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
    
    // Set configuration (files by default)
    foreach(array('app', 'feeds', 'calendars') as $conf) {
      if (!self::_setYamlConfig("$path/$conf.yml")) {
        self::_setDbConfig($conf);
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
   * Add config using YAML files
   * 
   * @param string $file
   * @return boolean
   */
  static protected function _setYamlConfig($file)
  {
    if (file_exists($file)) {
      self::_setConfig(Yaml::parse($file));
      return true;
    }
    
    return false;
  }
  
  /**
   * Add config using DB
   * [Be careful with circular dependency!!!]
   * 
   * @param string $conf
   * @return boolean
   */
  static protected function _setDbConfig($conf)
  {
    $dbConf = self::get("db.conf.$conf");
    $table = $dbConf['table'];
    $cols  = $dbConf['columns'];
    
    $query = App_Registry::db()->query($table, $cols);
    
    $data = array();
    while ($row = $query->fetch()) {
      if (!empty($row[$cols['src']])) {
        // Mandatory column
        $src = $row[$cols['src']];
        // Optional grouping by category
        if (isset($cols['cat'])) {
          // Set category
          $cat = empty($row[$cols['cat']]) ? '???' : $row[$cols['cat']];
          $data[$cat]['title'] = $cat;
          // Optional custom name
          if (!empty($row[$cols['key']])) {
            $data[$cat]['sources'][$row[$cols['key']]] = $src;
          } else {
            $data[$cat]['sources'][] = $src;
          }
        } else {
          $data[] = $src;
        }
      }
    }
    
    if (!empty($data)) {
      self::_setConfig(array($conf => $data));
      return true;
    }
    
    return false;
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