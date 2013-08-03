<?php

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;

class App_Db
{
  /** @var Connection **/
  protected $_conn = null;
  
  /**
   * @return Connection
   */
  public function getConnection()
  {
    if (!$this->_conn) {
      $this->_conn = DriverManager::getConnection(
        App_Registry::config()->get('db.conn'), 
        new Configuration()
      );
    }
    
    return $this->_conn;
  }

  /**
   * Execute basic query
   * 
   * @param string $table
   * @param array $columns
   * @return Statement
   */
  public function query($table, array $columns = array())
  {
    $sql = "SELECT " . (empty($columns) ? '*' : implode(',', $columns)) . " FROM $table";
    
    return $this->getConnection()->executeQuery($sql);
  }
}