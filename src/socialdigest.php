#!/usr/bin/env php
<?php

try {
  
  /** 
   * Init app
   */  
  require_once __DIR__ . '/lib/App.php';
  $app = new App();
  
  /**
   * Execution
   */
  $application = new Symfony\Component\Console\Application();
  $application->add($app);
  $application->run();
  
} catch (Exception $e) {
  echo $e->getMessage();
  exit(1);
}

exit(0);