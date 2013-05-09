<?php

use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

require_once __DIR__ . '/Symfony/Component/Console/Command/Command.php';

class App extends Command
{
  const TYPE_POST  = 'blogs';
  const TYPE_TWEET = 'tweets';
  const TYPE_EVENT = 'events';
  
  // Singleton pattern
  static protected $_instances = array();
  
  // Configuration
  protected $_filename = null;
  
  // Data
  protected $_datesMap = array();
  protected $_results = array();
  
  public function __construct() 
  {
    // Set paths
    require_once __DIR__ . '/Symfony/Component/ClassLoader/UniversalClassLoader.php';
    
    $loader = new UniversalClassLoader();
    
    $loader->registerNamespaces(array(
        'Symfony' => __DIR__,
    ));
    
    $loader->registerPrefixes(array(
        'App_'    => __DIR__,
    ));
    
    $loader->register();
    
    require_once __DIR__ . '/Swift/lib/swift_required.php';
    
    // Construct
    parent::__construct('socialdigest');
  }
  
  // Aux methods
  
  protected function _setConfigurationPath($path)
  {
    // Set configuration
    foreach(array('app', 'blogs', 'calendars') as $cnf) {
      $config = Yaml::parse("$path/$cnf.yml");
      App_Config::setConfig($config);
    }
    
    // Initialize output file
    $filename = $this->conf('app.output.file');
    
    if (!file_exists($filename)) {
      touch($filename);
    }
    
    file_put_contents($filename, "");
    $this->_filename = $filename;
  }
  
  static public function __callStatic($name, $arguments) 
  {
    $class = "App_" . ucfirst($name);
    
    if (empty(self::$_instances[$class])) {
      self::$_instances[$class] = new $class();
    }
    
    return self::$_instances[$class];
  }
  
  public function conf($name)
  {
    return App_Config::get($name);
  }
  
  public function output($str = null)
  {
    if ($str === null) {
      return file_get_contents($this->_filename);
    } else {
      return file_put_contents($this->_filename, $str, FILE_APPEND | LOCK_EX);
    }
  }
  
  public function mapDate($date)
  {
    $date = is_a($date, 'DateTime') ? $date : new DateTime($date);
    $hash = date_format($date, 'Y-m-d');
    
    if (!isset($this->_datesMap[$hash])) {
      $this->_datesMap[$hash] = self::view()->renderDate($date, true, false, true);
    }
  }
  
  // Command methods
  
  protected function configure()
  {
    $this ->setName('run')
          ->setDescription('Social digest system (HTML)')
          ->addArgument(
            'config',
            InputArgument::OPTIONAL,
            'Configuration files folder path'
          );
  }
  
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    // Get options
    $path = $input->getArgument('config') ?: __DIR__ . "/../conf";
    
    // Configure app
    $this->_setConfigurationPath($path);
    self::log()->setLogger($output);
    
    // Execute
    $this->results = array();
    $today = new DateTime();
    
    if ($this->conf('app.events.enabled')) {
      $this->getEvents($today);
    }

    if ($this->conf('app.blogs.enabled')) {
      $this->getFeeds($today);
    }
    
    if ($this->conf('app.tweets.enabled')) {
      $this->getTweets();
    }
    
    // Total

    self::log()->debug("TOTAL: " . count($this->results) . " section/s have updates");

    /**
     * Render
     */
    
    $title = $this->conf('app.title') . " ". date('d/m/Y');
    $mail  = $this->conf('app.output.mail.enabled');
    $full  = empty($mail) && $this->conf('app.output.full');

    $this->render($title, $full);
    
    if ($mail) {
      $this->sendMail($title);
    }
  }
  
  public function getFeeds($today)
  {
    self::log()->info("Obtaining BLOGS posts...");
    
    // Default filters
    $defaultMax = $this->conf('app.blogs.max');
    $defaultInt = $this->conf('app.blogs.interval');

    // Default data
    $defaultTit = $this->conf('app.blogs.title');
    $defaultUrl = $this->conf('app.blogs.url');

    // Default flags
    $defaultAut = $this->conf('app.blogs.author');
    $defaultDat = $this->conf('app.blogs.date');
    $defaultCat = $this->conf('app.blogs.category');

    foreach ($this->conf('blogs') as $section) {

      // Optional filters
      $tag = self::utils()->getArrayValue($section, 'tag');
      $max = self::utils()->getArrayValue($section, 'max', $defaultMax);
      $int = self::utils()->getArrayValue($section, 'interval', $defaultInt);

      // Optional data
      $tit = self::utils()->getArrayValue($section, 'title', $defaultTit);
      $url = self::utils()->getArrayValue($section, 'url', $defaultUrl);

      // Optional flags
      $aut = self::utils()->getArrayValue($section, 'author', $defaultAut);
      $dat = self::utils()->getArrayValue($section, 'date', $defaultDat);
      $cat = self::utils()->getArrayValue($section, 'category', $defaultCat);

      // Data
      $posts = array();
      $count = 0;

      // Read feeds
      foreach ($section['sources'] as $blog) {
        try {
          // Structure "url@type" (type is optional)
          $blog = explode('@', $blog);
          $resp = self::service()->getRss($blog[0], $tag, @$blog[1]);
          self::log()->debug("Obtaining rss feed from " . $blog[0]);
        } catch (Exception $e) {
          self::log()->err($e->getMessage());
        }

        if (!empty($resp)) {
          // Parse feed
          foreach ($resp->channel->item as $post) {
            // Check time interval
            $pubDateTime = new DateTime($post->pubDate);
            $start = self::utils()->getDateSub($today, $int);

            if ($pubDateTime >= $start) {
              // Using suffix to avoid key overriding
              $date = self::view()->renderDate($pubDateTime);
              $iKey = "{$post->pubDate}#$count";
              $item = 
                ($aut ? "[{$resp->channel->title}]" : "") . 
                ($dat ? "[$date]" : "") . " {$post->title} - " . 
                self::view()->renderLink(self::service()->getBitlyUrl($post->link));
              // Categories?
              if ($cat) {
                $category = "{$post->category}" ?: self::utils()->t("No category");
                $posts[$category][$iKey] = $item;
              } else {
                $posts[$iKey] = $item;
              }
              if ($count++ === $max) {
                break;
              }
            }
          }
        }

      } // end blogs foreach

      if (!empty($posts)) {

        if ($cat) {
          // Order posts by category
          ksort($posts);
          // Order category posts by date time
          foreach ($posts as &$catPosts) {
            krsort($catPosts);
          }
        } else {
          // Order posts by date time
          krsort($posts);
        }

        // Add results
        if (!isset($this->results[self::TYPE_POST])) {
          $this->results[self::TYPE_POST] = "";
        }
        $this->results[self::TYPE_POST] .= self::view()->renderArticle(
          $posts, 
          $tit, 
          $url ? self::service()->getBitlyUrl($url) : null
        );
      }
    } // end sections foreach    
  }

  public function getTweets()
  {
    self::log()->info("Obtaining TWITTER search...");
    
    $max = $this->conf('app.tweets.max');
    $tag = $this->conf("app.tweets.tag");
    $resp = self::service()->getTweets("$tag+exclude:retweets", $max);
    $count = 0;
    
    if (!empty($resp)) {

      self::log()->debug(count($resp->results) . " tweets received");

      foreach ($resp->results as $tw) {
        // Tweet status link
        $search = array('%username', '%id');
        $replac = array($tw->from_user, $tw->id_str);
        $statusUrl = $this->conf('services.twitter.urls.status');
        $statusUrl = str_replace($search, $replac, $statusUrl);
        $user = self::view()->renderLink($statusUrl, "@{$tw->from_user}");
        // Tweet date
        $dateTime = $tw->created_at;
        $dateKey = date_format(new DateTime($dateTime), 'Y-m-d');
        // Add result
        $this->results[self::TYPE_TWEET][$dateKey][] = "[$user] " . self::view()->renderTweet($tw->text);
        // Store days hashmap
        $this->mapDate($dateTime);
        // Check max limit
        if ($count++ === $max) {
          break;
        }
      }
    }
  }
  
  public function getEvents($today)
  {
    self::log()->info("Obtaining GOOGLE CALENDAR events...");
    
    $count = 0;
    $int = $this->conf('app.events.interval');

    foreach ($this->conf('calendars') as $calendar) {

      $end = self::utils()->getDateAdd($today, $int);
      $resp = self::service()->getGoogleCalendarEvents($calendar, $today, $end);

      if (!empty($resp)) {
        if (!empty($resp->items)) {
          foreach ($resp->items as $event) {
            if (!empty($event->start)) {
              $dateTime = isset($event->start->dateTime) ? $event->start->dateTime : $event->start->date;
              $dateKey = date_format(new DateTime($dateTime), 'Y-m-d');
              $hour = self::view()->renderDate($dateTime, false, true);
              // Using suffix to avoid key overriding
              $this->results[self::TYPE_EVENT][$dateKey][$hour . "#$count"] = "[{$hour}h] {$event->summary} - "
                . self::view()->renderLink(self::service()->getBitlyUrl($event->htmlLink));
              $count++;
              // Store days hashmap
              $this->mapDate($dateTime);
            }
          }
        } else {
          self::log()->debug("No events from $calendar");
        }
      } else {
        self::log()->err("Unable to get events from $calendar (" . json_encode($resp) . ")");
      }
    }
  }
  
  public function render($title, $full)
  {
    // Open tags?

    if ($full) {
      $this->output(
        '<html>
          <head>
            <title>' . $this->conf('app.title') . '</title>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <meta name="description" content="' . $this->conf('app.description') . '" />
          </head>
          <body>'
      );

      // Title and credits

      $this->output(
        "<header>" . PHP_EOL . 
          "<h1>" . $title . "</h1>" . PHP_EOL . 
        "</header>" . PHP_EOL
      );
    }

    // Sections

    $this->output("<section>" . PHP_EOL);
    foreach ($this->results as $type => $content) {
      // Default
      if (is_array($content)) {

        $tit = $this->conf("app.$type.title");
        $url = $this->conf("app.$type.url");
        
        if ($type === self::TYPE_TWEET && empty($url)) {
            $url = $this->conf('services.twitter.urls.search');
            $tag = $this->conf("app.tweets.tag");
            $url = str_replace('%query', urlencode($tag), $url);
        }

        if (!empty($content)) {
          // Order by date time
          ksort($content);
          // Internal order
          foreach ($content as $day => &$dailyContent) {
            ksort($dailyContent);
            // Change key to include weekday
            $orderedContent[$this->_datesMap[$day]] = $dailyContent;
            unset($content[$day]);
          }
        }
        // Render content
        $content = self::view()->renderArticle(
          $orderedContent, 
          $tit, 
          $url ? self::service()->getBitlyUrl($url) : null
        );
        // Free memory
        unset($orderedContent);
      }
      // Render
      $this->output($content);
      // Free memory
      unset($content);
    }
    $this->output("</section>" . PHP_EOL);

    // Footer

    $credits = $this->conf('app.credits');

    if (!empty($credits)) {
      
      // Self instance
      array_walk($credits, function(&$val, $key) {
        // Replace links
        $val = preg_replace_callback(
          '/([a-z]+:\/\/){0,1}[a-z0-9-_]+\.[a-z0-9-_:%&~\?\/.=]+/i', 
          function ($matches) {
            return App::service()->getBitlyUrl($matches[0]);
          },
          $val
        );
      });
      
      $this->output(
        "<footer>" . PHP_EOL . 
          "<hr/>" . self::view()->renderList($credits) . 
        "</footer>"
      );
    }

    // Close tags?

    if ($full) {
      $this->output(
        '  </body>
        </html>'
      );
    }
  }
  
  public function sendMail($title)
  {
    // Prepare delivery
    $message = self::mail()->createMessage();
    $to = $this->conf('app.output.mail.to');
    $message->setTo($to);
    $message->setSubject($title);
    $message->setBody(
      $this->output() . ($this->conf('app.output.mail.append') ?: ""), 
      'text/html'
    );
    // Optional values
    $recipients = array("to" => $to);
    foreach (array('from', 'cc', 'bcc') as $header) {
      $address = $this->conf("app.output.mail.$header");
      if (!empty($address)) {
        $method = 'set' . ucfirst($header);
        $message->{$method}($address);
        $recipients[$header] = $address;
      }
    }
    // Send e-mail
    self::log()->info("Sending mail (" . json_encode($recipients) . ")");
    $ok = self::mail()->sendMessage($message, true);
    // Success?
    if ($ok) {
      self::log()->info("Message sent ok!");
    } else {
      self::log()->err("Message delivery failed.");
    }    
  }  
}