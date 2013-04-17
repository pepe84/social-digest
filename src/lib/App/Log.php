<?php

class App_Log
{
  protected function _log($msg)
  {
    echo $msg . PHP_EOL;
  }
  
  public function info($msg)
  {
    return $this->_log("[INFO] " . $msg);    
  }
  
  public function err($msg)
  {
    return $this->_log("[ERR] " . $msg);
  }
  
  public function debug($msg)
  {
    return $this->_log("[DEBUG] " . $msg);    
  }
}