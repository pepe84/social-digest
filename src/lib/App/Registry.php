<?php

class App_Registry
{
  /** @var array **/
  static protected $_instances = array();
  
  static public function __callStatic($name, $arguments) 
  {
    $class = "App_" . ucfirst($name);
    
    if (empty(self::$_instances[$class])) {
      self::$_instances[$class] = new $class();
    }
    
    return self::$_instances[$class];
  }
}