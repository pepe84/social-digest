<?php

class App_Output
{
  /** @var string **/
  protected $_filename = null;
  
  public function init($filename) 
  {
    $this->_filename = $filename;
    
    if (!file_exists($this->_filename)) {
      touch($this->_filename);
    }
    
    file_put_contents($this->_filename, "");
  }
  
  /**
   * 
   * @return string
   */
  public function read() 
  {
    return file_get_contents($this->_filename);
  }  
  
  /**
   * 
   * @param string $content
   * @return int|boolean
   */
  public function write($content) 
  {
    return file_put_contents($this->_filename, $content, FILE_APPEND | LOCK_EX);
  }
}