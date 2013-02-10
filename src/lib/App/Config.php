<?php

class Config 
{
  static protected $_config = array();
  
  static public function setConfig($config)
  {
    self::$_config = $config;
  }
  
  static public function get($name) 
  {
    return isset(self::$_config[$name]) ? self::$_config[$name] : null;
  }
}