<?php

class App_Mail
{
  protected $_transport = null;
  
  /**
   *
   * @return Swift_Transport
   */
  public function getTransport()
  {
    if (!$this->_transport) {
      // Get mail configuration
      $cnf = App_Registry::config()->get('mail');
      
      if (!empty($cnf)) {
        // Custom transport
        $this->_transport = Swift_SmtpTransport::newInstance(
          isset($cnf['host']) ? $cnf['host'] : 'localhost',
          isset($cnf['port']) ? $cnf['port'] : 25,
          isset($cnf['encryption']) ? $cnf['encryption'] : 'ssl'
        );
        $this->_transport->setUsername($cnf['username']);
        $this->_transport->setPassword($cnf['password']);        
      } else {
        // Default
        $this->_transport = Swift_MailTransport::newInstance();
      }
    }
    
    return $this->_transport;
  }
  
  /**
   *
   * @return Swift_Message
   */
  public function createMessage()
  {
    return Swift_Message::newInstance();
  }
  
  /**
   *
   * @return boolean 
   */
  public function sendMessage(Swift_Message $message, $enableLogger = false)
  {
    // Create the Mailer using your created Transport
    $transport = $this->getTransport();
    $mailer = Swift_Mailer::newInstance($transport);
    
    if ($enableLogger) {
      // Using Echo Logger
      $logger = new Swift_Plugins_Loggers_EchoLogger();
      $mailer->registerPlugin(new Swift_Plugins_LoggerPlugin($logger));
    }
    
    // Send the message
    return (bool)$mailer->send($message);
  }
}