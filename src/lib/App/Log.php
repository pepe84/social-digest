<?php

use Symfony\Component\Console\Output\OutputInterface;

class App_Log
{
  /** @var OutputInterface **/
  protected $_logger = null;
  
  /**
   * @return OutputInterface
   */
  public function getLogger()
  {
    return $this->_logger;
  }
  
  /**
   * @param OutputInterface
   * @return App_Log
   */
  public function setLogger(OutputInterface $output)
  {
    $this->_logger = $output;
    
    return $this;
  }
  
  public function info($msg)
  {
    // green text
    return $this->_logger->writeln("<info>[info] $msg</info>");
  }
  
  public function err($msg)
  {
    // white text on a red background
    return $this->_logger->writeln("<error>[err] $msg</error>");
  }
  
  public function debug($msg)
  {
    // yellow text
    return $this->_logger->writeln("<comment>[debug] $msg</comment>");    
  }
}