<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class App_Command_Mail extends Command
{
  protected function configure()
  {
    $this ->setName('mail')
          ->setDescription('Social digest system (e-mail)')
          ->addArgument(
            'config',
            InputArgument::OPTIONAL,
            'Configuration files folder path or default'
          )
          ->addArgument(
            'addresses',
            InputArgument::IS_ARRAY,
            'from:a to:b,c cc:d,e,f bcc:g'
          );
  }
  
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    // Get options
    $path = $input->getArgument('config');
    
    // Configue app
    App_Registry::config()->init($path);
    
    // Dependency injection
    $filename = App_Registry::config()->get('app.output.file.path');
    App_Registry::output()->init($filename);
    App_Registry::log()->setLogger($output);
    
    // Execute
    $this->send($input->getArgument('addresses'));
  }
  
  // Aux methods
  
  public function getAddresses()
  {
    $addresses = array();
    
    foreach (array('from', 'to', 'cc', 'bcc') as $header) {
      $address = App_Registry::config()->get("app.output.mail.$header");
      if (!empty($address)) {
        $addresses[$header] = "$header:" . (is_array($address) ? implode(',', $address) : $address);
      }
    }
    
    return $addresses;
  }
  
  public function send(array $addresses = array(), $includeConfAddresses = false)
  { 
    // Use config?
    $groups[] = $addresses;
    
    if ($includeConfAddresses) {
      $groups[] = $this->getAddresses();
    }
    
    // Prepare delivery
    $message = App_Registry::mail()->createMessage();
    $message->setSubject(
      App_Registry::config()->get('app.title') . " ". date('d/m/Y')
    );
    $message->setBody(
      App_Registry::output()->read() . 
        (App_Registry::config()->get('app.output.mail.append') ?: ""), 
      'text/html'
    );
        
    // Adresses
    $log = array();
    
    foreach ($groups as $group) {
      foreach ($group as $subaddresses) {
        if (!empty($subaddresses)) {
          // Split header
          list($header, $list) = explode(':', $subaddresses);
          $log[$header][] = $list;
          // Split addresses
          $list = explode(',', $list);
          array_walk($list, 'trim');
          // Add to message
          $method = 'set' . ucfirst($header);
          $message->{$method}($list);
        }
      }
    }
    
    // Send e-mail
    App_Registry::log()->info("Sending mail " . print_r($log, true));
    $success = App_Registry::mail()->sendMessage($message);
    
    if ($success) {
      App_Registry::log()->info("Message sent ok!");
    } else {
      App_Registry::log()->err("Message delivery failed.");
    }    
  }  
}