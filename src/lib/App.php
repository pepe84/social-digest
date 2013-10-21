<?php

use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\Console\Application;

class App
{
  static public function bootstrap() 
  {
    // Set paths
    require_once __DIR__ . '/Symfony/Component/ClassLoader/UniversalClassLoader.php';
    
    $loader = new UniversalClassLoader();
    
    $loader->registerNamespaces(array(
        'Symfony' => __DIR__,
        'Doctrine' => __DIR__,
    ));
    
    $loader->registerPrefixes(array(
        'App_'    => __DIR__,
    ));
    
    $loader->register();
    
    require_once __DIR__ . '/Swift/lib/swift_required.php';
    require_once __DIR__ . '/SG-iCalendar/SG_iCal.php';
  }
  
  static public function run()
  {
    $application = new Application();
    $application->add(new App_Command_Digest());
    $application->add(new App_Command_Mail());
    $application->run();
  }
}