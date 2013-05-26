<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class App_Command_Digest extends Command
{
  const TYPE_POST  = 'blogs';
  const TYPE_TWEET = 'tweets';
  const TYPE_EVENT = 'events';
  
  protected $_datesMap = array();
  protected $_results = array();
  
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
    $path = $input->getArgument('config') ?: __DIR__ . "/../../../conf";
    
    // Configue app
    App_Registry::config()->init($path);
    
    // Dependency injection
    $filename = App_Registry::config()->get('app.output.file');
    App_Registry::output()->init($filename);
    App_Registry::output()->clear($filename);
    App_Registry::log()->setLogger($output);
    
    // Execute
    $this->results = array();
    $today = new DateTime();
    
    if (App_Registry::config()->get('app.events.enabled')) {
      $this->getEvents($today);
    }

    if (App_Registry::config()->get('app.blogs.enabled')) {
      $this->getFeeds($today);
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
    $full  = empty($mail) && App_Registry::config()->get('app.output.full');

    $this->render($title, $full);
    
    if ($mail) {
      $this->sendMail($title);
    }
  }
  
  // Aux methods
  
  public function getFeeds($today)
  {
    App_Registry::log()->info("Obtaining BLOGS posts...");
    
    // Default filters
    $defaultMax = App_Registry::config()->get('app.blogs.max');
    $defaultInt = App_Registry::config()->get('app.blogs.interval');

    // Default data
    $defaultTit = App_Registry::config()->get('app.blogs.title');
    $defaultUrl = App_Registry::config()->get('app.blogs.url');
    
    // Default flags
    $defaultAut = App_Registry::config()->get('app.blogs.author');
    $defaultDat = App_Registry::config()->get('app.blogs.date');
    $defaultCat = App_Registry::config()->get('app.blogs.category');

    foreach (App_Registry::config()->get('blogs') as $name => $section) {
      
      App_Registry::log()->info("Starting $name section...");
      
      // Optional filters
      $tag = App_Registry::utils()->getArrayValue($section, 'tag');
      $max = App_Registry::utils()->getArrayValue($section, 'max', $defaultMax);
      $int = App_Registry::utils()->getArrayValue($section, 'interval', $defaultInt);

      // Optional data
      $tit = App_Registry::utils()->getArrayValue($section, 'title', $defaultTit);
      $url = App_Registry::utils()->getArrayValue($section, 'url', $defaultUrl);

      // Optional flags
      $aut = App_Registry::utils()->getArrayValue($section, 'author', $defaultAut);
      $dat = App_Registry::utils()->getArrayValue($section, 'date', $defaultDat);
      $cat = App_Registry::utils()->getArrayValue($section, 'category', $defaultCat);

      // Data
      $posts = array();
      
      // Read feeds
      foreach ($section['sources'] as $author => $blog) {
        try {
          // Structure "url@type" (type is optional)
          $blog = explode('@', $blog);
          $resp = App_Registry::service()->getRss($blog[0], $tag, @$blog[1]);
          App_Registry::log()->debug("Obtaining rss feed from " . $blog[0]);
        } catch (Exception $e) {
          App_Registry::log()->err($e->getMessage());
        }
        
        if (!empty($resp)) {
          // Reset counter
          $count = 0;
          // Get author from RSS information
          if (is_numeric($author)) {
            $elem = isset($resp->title) ? $resp->title : $resp->channel->title;
            $author = trim("{$elem}");
          }
          // Parse feed
          foreach (isset($resp->entry) ? $resp->entry : $resp->channel->item as $post) {
            // Check time interval
            $pubDateTime = new DateTime($post->pubDate);
            $start = App_Registry::utils()->getDateSub($today, $int);

            if ($pubDateTime >= $start) {
              // Using suffix to avoid key overriding
              $date = App_Registry::view()->renderDate($pubDateTime);
              $iKey = "{$post->pubDate}#$count";
              $item = ($aut && $cat ? "[$author]" : "") . ($dat ? "[$date]" : "") . " {$post->title} - " . 
                App_Registry::view()->renderLink(App_Registry::service()->getBitlyUrl($post->link));
              // Categories?
              if ($cat) {
                $category = "{$post->category}" ?: App_Registry::utils()->t("No category");
                $posts[$category][$iKey] = $item;
              } else if ($aut) {
                $posts[$author][$iKey] = $item;
              } else {
                $posts[$iKey] = $item;
              }
              if ($count++ === $max-1) {
                break;
              }
            }
          }
        }

      } // end blogs foreach

      if (!empty($posts)) {

        if ($cat || $aut) {
          // Order posts by category / author
          ksort($posts);
          // Order posts by date time
          foreach ($posts as &$subPosts) {
            krsort($subPosts);
          }
        } else {
          // Order posts by date time
          krsort($posts);
        }
        
        // Add results
        if (!isset($this->results[self::TYPE_POST])) {
          $this->results[self::TYPE_POST] = "";
        }
        $this->results[self::TYPE_POST] .= App_Registry::view()->renderArticle(
          $posts, 
          $tit, 
          $url ? App_Registry::service()->getBitlyUrl($url) : null
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
        $statusUrl = App_Registry::config()->get('services.twitter.urls.status');
        $statusUrl = str_replace($search, $replac, $statusUrl);
        $user = App_Registry::view()->renderLink($statusUrl, "@{$tw->from_user}");
        // Tweet date
        $dateTime = $tw->created_at;
        $dateKey = date_format(new DateTime($dateTime), 'Y-m-d');
        // Add result
        $this->results[self::TYPE_TWEET][$dateKey][] = "[$user] " . App_Registry::view()->renderTweet($tw->text);
        // Store days hashmap
        $this->mapDate($dateTime);
        // Check max limit
        if ($count++ === $max-1) {
          break;
        }
      }
    }
  }
  
  public function getEvents($today)
  {
    App_Registry::log()->info("Obtaining GOOGLE CALENDAR events...");
    
    $count = 0;
    $int = App_Registry::config()->get('app.events.interval');

    foreach (App_Registry::config()->get('calendars') as $calendar) {

      $end = App_Registry::utils()->getDateAdd($today, $int);
      $resp = App_Registry::service()->getGoogleCalendarEvents($calendar, $today, $end);

      if (!empty($resp)) {
        if (!empty($resp->items)) {
          foreach ($resp->items as $event) {
            if (!empty($event->start)) {
              $dateTime = isset($event->start->dateTime) ? $event->start->dateTime : $event->start->date;
              $dateKey = date_format(new DateTime($dateTime), 'Y-m-d');
              $hour = App_Registry::view()->renderDate($dateTime, false, true);
              // Using suffix to avoid key overriding
              $this->results[self::TYPE_EVENT][$dateKey][$hour . "#$count"] = "[{$hour}h] {$event->summary} - "
                . App_Registry::view()->renderLink(App_Registry::service()->getBitlyUrl($event->htmlLink));
              $count++;
              // Store days hashmap
              $this->mapDate($dateTime);
            }
          }
        } else {
          App_Registry::log()->debug("No events from $calendar");
        }
      } else {
        App_Registry::log()->err("Unable to get events from $calendar (" . json_encode($resp) . ")");
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
        "<header>" . PHP_EOL . 
          "<h1>" . $title . "</h1>" . PHP_EOL . 
        "</header>" . PHP_EOL
      );
    }

    // Sections
    
    App_Registry::output()->write("<section>" . PHP_EOL);
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
        $content = App_Registry::view()->renderArticle(
          $orderedContent, 
          $tit, 
          $url ? App_Registry::service()->getBitlyUrl($url) : null
        );
        // Free memory
        unset($orderedContent);
      }
      // Render
      App_Registry::output()->write($content);
      // Free memory
      unset($content);
    }
    App_Registry::output()->write("</section>" . PHP_EOL);

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
        "<footer>" . PHP_EOL . 
          "<hr/>" . App_Registry::view()->renderList($credits) . 
        "</footer>"
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
  
  public function mapDate($date)
  {
    $date = is_a($date, 'DateTime') ? $date : new DateTime($date);
    $hash = date_format($date, 'Y-m-d');
    
    if (!isset($this->_datesMap[$hash])) {
      $this->_datesMap[$hash] = App_Registry::view()->renderDate($date, true, false, true);
    }
  }
  
  public function sendMail($title)
  {
    // Prepare delivery
    $message = App_Registry::mail()->createMessage();
    $to = App_Registry::config()->get('app.output.mail.to');
    $message->setTo($to);
    $message->setSubject($title);
    $message->setBody(
      App_Registry::output()->read() . (App_Registry::config()->get('app.output.mail.append') ?: ""), 
      'text/html'
    );
    // Optional values
    $recipients = array("to" => $to);
    foreach (array('from', 'cc', 'bcc') as $header) {
      $address = App_Registry::config()->get("app.output.mail.$header");
      if (!empty($address)) {
        $method = 'set' . ucfirst($header);
        $message->{$method}($address);
        $recipients[$header] = $address;
      }
    }
    // Send e-mail
    App_Registry::log()->info("Sending mail (" . json_encode($recipients) . ")");
    $ok = App_Registry::mail()->sendMessage($message, true);
    // Success?
    if ($ok) {
      App_Registry::log()->info("Message sent ok!");
    } else {
      App_Registry::log()->err("Message delivery failed.");
    }    
  }  
}