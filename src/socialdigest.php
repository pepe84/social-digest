#!/usr/bin/env php
<?php

try {
  
  require_once __DIR__ . '/lib/App.php';
  App::bootstrap();
  App::run();
  
} catch (Exception $e) {
  echo $e->getMessage();
  exit(1);
}

exit(0);