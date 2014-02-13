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
    
    // Split header and addresses
    $data = $input->getArgument('addresses');
    
    foreach ($data as $subdata) {
      list($header, $mails) = explode(':', $subdata);
      $mailsArray = explode(',', $mails);
      array_walk($mailsArray, 'trim');
      $addresses[$header] = $mailsArray;
    }
    
    // Execute
    $this->send($addresses);
  }
  
  // Aux methods
  
  public function send(array $addresses = array(), $includeConfAddresses = false)
  { 
    // Use config?
    if ($includeConfAddresses) {
      $conf = App_Registry::config()->get("app.output.mail");
      $this->mergeAddresses($addresses, $conf);
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
    
    foreach ($addresses as $header => $mails) {
      // Add to message
      $method = 'set' . ucfirst($header);
      $message->{$method}($mails);
      $log[$header] = implode (', ', $mails);
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
  
  
  public function mergeAddresses(array &$data1, array $data2)
  {
    foreach (array('from', 'to', 'cc', 'bcc') as $header) {
      if (!empty($data2[$header])) {
        if (isset($data1[$header])) {
          $data1[$header] = array_merge($data1[$header], $data2[$header]);
        } else {
          $data1[$header] = $data2[$header];
        }
      }
    }
  }

}