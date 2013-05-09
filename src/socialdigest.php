#!/usr/bin/env php
<?php

define('TYPE_POST',  'blogs');
define('TYPE_TWEET', 'tweets');
define('TYPE_EVENT', 'events');

try {
  
  /** 
   * Init app
   */
  
  require_once __DIR__ . '/lib/App.php';
  new App();
  
  /** 
   * Execution
   */
  
  $results = array();
  $today = new DateTime();
  $dayMap = array();
  
  if (App::conf('app.events.enabled')) {
    // Get events
    App::log()->debug("Obtaining GOOGLE CALENDAR info...");
    
    $count = 0;
    $int = App::conf('app.events.interval');
      
    foreach (App::conf('calendars') as $calendar) {
      
      $end = App::utils()->getDateAdd($today, $int);
      $resp = App::service()->getGoogleCalendarEvents($calendar, $today, $end);
      
      if (!empty($resp) && !empty($resp->items)) {
        foreach ($resp->items as $event) {
          if (!empty($event->start)) {
            $dateTime = isset($event->start->dateTime) ? $event->start->dateTime : $event->start->date;
            $dateKey = date_format(new DateTime($dateTime), 'Y-m-d');
            $hour = App::view()->renderDate($dateTime, false, true);
            // Using suffix to avoid key overriding
            $results[TYPE_EVENT][$dateKey][$hour . "#$count"] = "[{$hour}h] {$event->summary} - "
              . App::view()->renderLink(App::service()->getBitlyUrl($event->htmlLink));
            $count++;
            // Store days hashmap
            App::mapDate($dateTime);
          }
        }
      } else {
        App::log()->err("No events from $calendar (" . json_encode($resp) . ")");
      }
    }
  }
  
  if (App::conf('app.blogs.enabled')) {
    
    // Get blogs' posts
    App::log()->debug("Obtaining BLOGS info...");
    
    // Default filters
    $defaultMax = App::conf('app.blogs.max');
    $defaultInt = App::conf('app.blogs.interval');
    
    // Default data
    $defaultTit = App::conf('app.blogs.title');
    $defaultUrl = App::conf('app.blogs.url');
    
    // Default flags
    $defaultAut = App::conf('app.blogs.author');
    $defaultDat = App::conf('app.blogs.date');
    $defaultCat = App::conf('app.blogs.category');
    
    foreach (App::conf('blogs') as $section) {
      
      // Optional filters
      $tag = App::utils()->getArrayValue($section, 'tag');
      $max = App::utils()->getArrayValue($section, 'max', $defaultMax);
      $int = App::utils()->getArrayValue($section, 'interval', $defaultInt);
      
      // Optional data
      $tit = App::utils()->getArrayValue($section, 'title', $defaultTit);
      $url = App::utils()->getArrayValue($section, 'url', $defaultUrl);
      
      // Optional flags
      $aut = App::utils()->getArrayValue($section, 'author', $defaultAut);
      $dat = App::utils()->getArrayValue($section, 'date', $defaultDat);
      $cat = App::utils()->getArrayValue($section, 'category', $defaultCat);
      
      // Data
      $posts = array();
      $count = 0;
      
      // Read feeds
      foreach ($section['sources'] as $blog) {
        try {
          // Structure "url@type" (type is optional)
          $blog = explode('@', $blog);
          $resp = App::service()->getRss($blog[0], $tag, @$blog[1]);
          App::log()->debug("Obtaining rss feed from " . $blog[0]);
        } catch (Exception $e) {
          App::log()->err($e->getMessage());
        }
        
        if (!empty($resp)) {
          // Parse feed
          foreach ($resp->channel->item as $post) {
            // Check time interval
            $pubDateTime = new DateTime($post->pubDate);
            $start = App::utils()->getDateSub($today, $int);

            if ($pubDateTime >= $start) {
              // Using suffix to avoid key overriding
              $date = App::view()->renderDate($pubDateTime);
              $iKey = "{$post->pubDate}#$count";
              $item = 
                ($aut ? "[{$resp->channel->title}]" : "") . 
                ($dat ? "[$date]" : "") . " {$post->title} - " . 
                App::view()->renderLink(App::service()->getBitlyUrl($post->link));
              // Categories?
              if ($cat) {
                $category = "{$post->category}" ?: App::utils()->t("No category");
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
        if (!isset($results[TYPE_POST])) {
          $results[TYPE_POST] = "";
        }
        $results[TYPE_POST] .= App::view()->renderArticle(
          $posts, 
          $tit, 
          $url ? App::service()->getBitlyUrl($url) : null
        );
      }
    } // end sections foreach    
  }
  
  if (App::conf('app.tweets.enabled')) {
    // Get tweets
    App::log()->debug("Obtaining TWITTER info...");
    
    $max = App::conf('app.tweets.max');
    $tag = App::conf("app.tweets.tag");
    $resp = App::service()->getTweets("$tag+exclude:retweets", $max);
    $count = 0;
    
    if (!empty($resp)) {
      
      App::log()->debug(count($resp->results) . " tweets received");
      
      foreach ($resp->results as $tw) {
        // Tweet status link
        $search = array('%username', '%id');
        $replac = array($tw->from_user, $tw->id_str);
        $statusUrl = App::conf('services.twitter.urls.status');
        $statusUrl = str_replace($search, $replac, $statusUrl);
        $user = App::view()->renderLink($statusUrl, "@{$tw->from_user}");
        // Tweet date
        $dateTime = $tw->created_at;
        $dateKey = date_format(new DateTime($dateTime), 'Y-m-d');
        // Add result
        $results[TYPE_TWEET][$dateKey][] = "[$user] " . App::view()->renderTweet($tw->text);
        // Store days hashmap
        App::mapDate($dateTime);
        // Check max limit
        if ($count++ === $max) {
          break;
        }
      }
    }
  }
  
  // Total
  
  App::log()->debug("TOTAL: " . count($results) . " section/s have updates");
    
  /**
   * Render
   */
  
  $title = App::conf('app.title') . " ". date('d/m/Y');
  $mail  = App::conf('app.output.mail.enabled');
  $full  = empty($mail) && App::conf('app.output.full');
  
  // Open tags?
  
  if ($full) {
    App::output(
      '<html>
        <head>
          <title>' . App::conf('app.title') . '</title>
          <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
          <meta name="description" content="' . App::conf('app.description') . '" />
        </head>
        <body>'
    );
    
    // Title and credits

    App::output(
      "<header>" . PHP_EOL . 
        "<h1>" . $title . "</h1>" . PHP_EOL . 
      "</header>" . PHP_EOL
    );
  }
  
  // Sections
  
  App::output("<section>" . PHP_EOL);
  foreach ($results as $type => $content) {
    // Default
    if (is_array($content)) {
      
      $tit = App::conf("app.$type.title");
      $url = App::conf("app.$type.url");
      
      if ($type === TYPE_TWEET && empty($url)) {
          $url = App::conf('services.twitter.urls.search');
          $url = str_replace('%query', urlencode($tag), $url);
      }
      
      if (!empty($content)) {
        // Order by date time
        ksort($content);
        // Internal order
        foreach ($content as $day => &$dailyContent) {
          ksort($dailyContent);
          // Change key to include weekday
          $orderedContent[App::$datesMap[$day]] = $dailyContent;
          unset($content[$day]);
        }
      }
      // Render content
      $content = App::view()->renderArticle(
        $orderedContent, 
        $tit, 
        $url ? App::service()->getBitlyUrl($url) : null
      );
      // Free memory
      unset($orderedContent);
    }
    // Render
    App::output($content);
    // Free memory
    unset($content);
  }
  App::output("</section>" . PHP_EOL);
  
  // Footer
  
  $credits = App::conf('app.credits');
  
  if (!empty($credits)) {
    
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

    App::output(
      "<footer>" . PHP_EOL . 
        "<hr/>" . App::view()->renderList($credits) . 
      "</footer>"
    );
  }
  
  // Close tags?
  
  if ($full) {
    App::output(
      '  </body>
      </html>'
    );
  }
  
  // Send e-mail?
  
  if (!empty($mail)) {
    // Prepare delivery
    $message = App::mail()->createMessage();
    $to = App::conf('app.output.mail.to');
    $message->setTo($to);
    $message->setSubject($title);
    $message->setBody(
      App::output() . (App::conf('app.output.mail.append') ?: ""), 
      'text/html'
    );
    // Optional values
    $recipients = "to:$to";
    foreach (array('from', 'cc', 'bcc') as $header) {
      $address = App::conf("app.output.mail.$header");
      if (!empty($address)) {
        $method = 'set' . ucfirst($header);
        $message->{$method}($address);
        $recipients .= ", $header:$address";
      }
    }
    // Send e-mail
    App::log()->debug("Sending mail ($recipients)");
    $ok = App::mail()->sendMessage($message, true);
    // Success?
    if ($ok) {
      App::log()->debug("Message sent ok!");
    } else {
      App::log()->err("Message delivery failed.");
    }
  }
  
} catch (Exception $e) {
  App::log()->err($e->getMessage());
  exit(1);
}

exit(0);