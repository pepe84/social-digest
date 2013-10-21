<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class App_Command_Digest extends Command
{
  const TYPE_POST  = 'news';
  const TYPE_TWEET = 'tweets';
  const TYPE_EVENT = 'events';
  
  protected $_datesMap = array();
  protected $_results = array();
  
  protected function configure()
  {
    // Command
    $this ->setName('run')
          ->setDescription('Social digest system (HTML)')
          ->addArgument(
            'config',
            InputArgument::OPTIONAL,
            'Configuration files folder path or default'
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
    App_Registry::output()->clear($filename);
    App_Registry::log()->setLogger($output);
    $style = App_Registry::config()->get('app.output.style.inline');
    App_Registry::view()->setInlineStyle($style ?: array());
    
    // Execute
    $this->results = array();
    $today = new DateTime();
    
    if (App_Registry::config()->get('app.events.enabled')) {
      $this->getEvents($today);
    }

    if (App_Registry::config()->get('app.news.enabled')) {
      $this->getNews($today);
    }
    
    if (App_Registry::config()->get('app.tweets.enabled')) {
      $this->getTweets();
    }
    
    // Total

    App_Registry::log()->info("TOTAL: " . count($this->results) . " section/s have updates");
    
    /**
     * Render
     */
    
    $title = App_Registry::config()->get('app.title') . " ". date('d/m/Y');
    $mail  = App_Registry::config()->get('app.output.mail.enabled');
    $full  = empty($mail) && App_Registry::config()->get('app.output.file.full');

    $this->render($title, $full);
    
    if ($mail) {
      $this->sendMail($title);
    }
  }
  
  // Aux methods
  
  public function getNews($today)
  {
    App_Registry::log()->info("Obtaining FEEDS...");
    
    // Default config
    $default = App_Registry::config()->get('app.news');
    
    foreach (App_Registry::config()->get('feeds') as $section => $data) {
      
      App_Registry::log()->info("Starting section '$section'...");
      
      // Optional data
      $url = App_Registry::utils()->getArrayValue($data, 'url', @$default['url']);
      
      // Optional filters
      $max = App_Registry::utils()->getArrayValue($data, 'max', @$default['max']);
      $int = App_Registry::utils()->getArrayValue($data, 'interval', @$default['interval']);
      $tag = App_Registry::utils()->getArrayValue($data, 'tag', @$default['tag']);
      
      // Optional flags
      $aut = App_Registry::utils()->getArrayValue($data, 'author', @$default['author']);
      $dat = App_Registry::utils()->getArrayValue($data, 'date', @$default['date']);
      
      // Data
      $posts = array();
      $inc = 0;
      
      // Read feeds
      foreach ($data['sources'] as $author => $src) {
        try {
          // Allowing only one source... 
          if (is_array($src)) {
            $src = array_shift($src);
          }
          // Structure "url@type" (type is optional)
          $src = explode('@', $src);
          $resp = App_Registry::service()->getRss($src[0], $tag, @$src[1]);
          App_Registry::log()->debug("Obtaining rss feed from " . $src[0]);
        } catch (Exception $e) {
          App_Registry::log()->err($e->getMessage());
        }
        
        if (!empty($resp)) {
          // Reset counter
          $count = 0;
          // Get author from RSS information
          if (is_numeric($author)) {
            $elem = $resp->channel->title ?: $resp->title ?: "";
            $author = trim("{$elem}") ?: App_Registry::utils()->t("No author");
          }
          // Parse feed
          foreach ($resp->channel->item ?: $resp->entry ?: array() as $post) {
            // Check time interval
            $pubDateTime = new DateTime($post->pubDate);
            $start = App_Registry::utils()->getDateSub($today, $int);

            if ($pubDateTime >= $start) {
              // Construct info
              $item = ($dat ? "[" . App_Registry::view()->renderDate($pubDateTime) . "]" : "") . 
                (!$aut ? "[$author]" : "") . " {$post->title} - " . 
                App_Registry::view()->renderLink(App_Registry::service()->getBitlyUrl("{$post->link}"));
              // Using suffix to avoid key overriding
              $dateKey = $this->_getDateKey($pubDateTime) . "#{$inc}";
              $inc++;
              // Categories?
              if ($aut) {
                $posts[$author][$dateKey] = $item;
              } else {
                $posts[$dateKey] = $item;
              }
              if ($count++ === $max-1) {
                break;
              }
            }
          }
        }

      } // end feeds foreach

      if (!empty($posts)) {
        
        // Optional date order
        $rev = @$default['reverse'];
        
        if ($aut) {
          // Order posts by category / author
          ksort($posts);
          // Order posts by date time
          foreach ($posts as &$subPosts) {
            $rev ? krsort($subPosts) : ksort($subPosts);
          }
        } else {
          // Order posts by date time
          $rev ? krsort($posts) : ksort($posts);
        }
        
        // Add results
        if (!isset($this->results[self::TYPE_POST])) {
          $this->results[self::TYPE_POST] = "";
        }
        $this->results[self::TYPE_POST] .= App_Registry::view()->renderSection(
          $posts, 
          $section, 
          $url ? App_Registry::service()->getBitlyUrl($url) : null,
          self::TYPE_POST
        );
      }
    } // end sections foreach    
  }

  public function getTweets()
  {
    App_Registry::log()->info("Obtaining TWITTER search...");
    
    $max = App_Registry::config()->get('app.tweets.max');
    $tag = App_Registry::config()->get("app.tweets.tag");
    $resp = App_Registry::service()->getTweets("$tag+exclude:retweets", $max);
    $count = 0;
    
    if (!empty($resp)) {

      App_Registry::log()->debug(count($resp->results) . " tweets received");

      foreach ($resp->results as $tw) {
        // Tweet status link
        $search = array('%username', '%id');
        $replac = array($tw->from_user, $tw->id_str);
        $statusUrl = str_replace($search, $replac, App_Registry::config()->get('services.twitter.urls.status'));
        $user = App_Registry::view()->renderLink($statusUrl, "@{$tw->from_user}");
        // Tweet date
        $dateTime = $tw->created_at;
        $dateKey = $this->_getDateKey($dateTime);
        // Add result
        $this->results[self::TYPE_TWEET][$dateKey][] = "[$user] " . App_Registry::view()->renderTweet($tw->text);
        // Store days hashmap
        $this->_mapDate($dateTime);
        // Check max limit
        if ($count++ === $max-1) {
          break;
        }
      }
    }
  }
  
  public function getEvents($today)
  {
    App_Registry::log()->info("Obtaining EVENTS...");
    
    $count = 0;
    $int = App_Registry::config()->get('app.events.interval');
    $end = App_Registry::utils()->getDateAdd($today, $int);

    foreach (App_Registry::config()->get('calendars') as $calendar) {
      
      if (preg_match('/^(.+)\.ics(\?.+)?$/i', $calendar)) {
        // iCalendar format
        $items = App_Registry::service()->getIcalEvents($calendar, $today, $end);
      } else {
        // Google Calendar
        $resp = App_Registry::service()->getGoogleCalendarEvents($calendar, $today, $end);
        if (empty($resp)) {
          App_Registry::log()->err("Unable to get events from $calendar (" . json_encode($resp) . ")");
          continue;
        } else {
          $items = !empty($resp->items) ? $resp->items : array();
        }
      }
      
      if (!empty($items)) {
        App_Registry::log()->info(count($items) . " events from $calendar");        
        
        foreach ($items as $event) {
          $event = \App_Model_Event::factory($event);
          $dateDay  = date_format($event->startDate, 'Y-m-d');
          $dateHour = date_format($event->startDate, 'H:i');
          // Using suffix to avoid key overriding
          $this->results[self::TYPE_EVENT][$dateDay][$dateHour . "#$count"] = 
            ($event->allDay ? "" : "[{$dateHour}h] ") . 
            "{$event->summary}" . ($event->location ? " » {$event->location}" : "") . 
            ($event->link ? " - " . App_Registry::view()->renderLink(App_Registry::service()->getBitlyUrl($event->link)) : "");
          $count++;
          // Store days hashmap
          $this->_mapDate($event->startDate);
        }
      } else {
        App_Registry::log()->debug("No events from $calendar");
      }
    }
  }
  
  public function render($title, $full)
  {
    // Open tags?

    if ($full) {
      App_Registry::output()->write(
        '<html>
          <head>
            <title>' . App_Registry::config()->get('app.title') . '</title>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <meta name="description" content="' . App_Registry::config()->get('app.description') . '" />
          </head>
          <body>'
      );

      // Title and credits

      App_Registry::output()->write(
        App_Registry::view()->renderTitle($title)
      );
    }
    
    // CSS style
    
    $stack = array(
      App_Registry::config()->get('app.output.style.path'),
      App_Registry::config()->getPath() . '/style.css',
      App_Registry::config()->getDefaultPath() . '/style.css'
    );
    
    foreach ($stack as $filepath) {
      if (file_exists($filepath)) {
        App_Registry::output()->write(
          App_Registry::view()->renderTag('style', file_get_contents($filepath))
        );
        break;
      }
    }
    
    // Sections
    
    foreach ($this->results as $type => $content) {
      // Default
      if (is_array($content)) {

        $tit = App_Registry::config()->get("app.$type.title");
        $url = App_Registry::config()->get("app.$type.url");
        
        if ($type === self::TYPE_TWEET && empty($url)) {
            $url = App_Registry::config()->get('services.twitter.urls.search');
            $tag = App_Registry::config()->get("app.tweets.tag");
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
        $content = App_Registry::view()->renderSection(
          $orderedContent, 
          $tit, 
          $url ? App_Registry::service()->getBitlyUrl($url) : null,
          $type
        );
        // Free memory
        unset($orderedContent);
      }
      // Render
      App_Registry::output()->write($content);
      // Free memory
      unset($content);
    }

    // Footer

    $credits = App_Registry::config()->get('app.credits');

    if (!empty($credits)) {
      
      // Self instance
      array_walk($credits, function(&$val, $key) {
        // Replace links
        $val = preg_replace_callback(
          '/([a-z]+:\/\/){0,1}[a-z0-9-_]+\.[a-z0-9-_:%&~\?\/.=]+/i', 
          function ($matches) {
            return App_Registry::service()->getBitlyUrl($matches[0]);
          },
          $val
        );
      });
      
      App_Registry::output()->write(
        App_Registry::view()->renderSection($credits, null, null, 'credits')
      );
    }

    // Close tags?

    if ($full) {
      App_Registry::output()->write(
        '  </body>
        </html>'
      );
    }
  }
  
  public function sendMail()
  {
    $command = new App_Command_Mail();
    $command->send();
  }
  
  protected function _getDateKey($date)
  {
    $date = is_a($date, 'DateTime') ? $date : new DateTime($date);
    return date_format($date, 'Y-m-d');
  }
  
  protected function _mapDate($date)
  {
    $hash = $this->_getDateKey($date);
    
    if (!isset($this->_datesMap[$hash])) {
      $this->_datesMap[$hash] = App_Registry::view()->renderDate($date, TRUE, FALSE, TRUE);
    }
  }
}