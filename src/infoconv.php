#!/usr/bin/env php
<?php

define('TYPE_POST',  'blogs');
define('TYPE_TWEET', 'tweets');
define('TYPE_EVENT', 'events');

try {
  
  /** 
   * Init app
   */
  
  require_once 'lib/App.php';
  new App(parse_ini_file('conf/app.ini'));
  
  /** 
   * Execution
   */
  
  $results = array();
  $tag = App::conf('app.tag');
  $today = new DateTime();
  
  if (App::conf('app.blogs.enabled')) {
    // Get blogs' posts
    App::log()->debug("Obtaining BLOGS info...");
    
    foreach (App::conf('blogs') as $blog) {
      // Read feed
      try {
        // Structure "url@type" (type is optional)
        $blog = explode('@', $blog);
        $resp = App::service()->getRss($blog[0], $tag, @$blog[1]);
      } catch (Exception $e) {
        App::log()->err($e->getMessage());
      }
      
      if (!empty($resp)) {
        // Parse feed
        $count = 0;
        $max = App::conf('app.blogs.max');
        $int = App::conf('app.blogs.interval');
        
        foreach ($resp->channel->item as $post) {
          // Check time interval
          $pubDateTime = new DateTime($post->pubDate);
          $start = App::utils()->getDateSub($today, $int);
          
          if ($pubDateTime >= $start) {
            // Using suffix to avoid key overriding
            $date = App::utils()->getDateStr($pubDateTime);
            $results[TYPE_POST][$post->pubDate . "#$count"] = "[{$resp->channel->title}][$date] {$post->title} - "
              . App::view()->renderLink(App::service()->getBitlyUrl($post->link));
            if ($count++ === $max) break;            
          }
        }
        // Order by date time
        if (!empty($results[TYPE_POST])) {
          krsort($results[TYPE_POST]);
        }
      }
    }    
  }
  
  if (App::conf('app.tweets.enabled')) {
    // Get tweets
    App::log()->debug("Obtaining TWITTER info...");
    
    $count = 0;
    $max = App::conf('app.tweets.max');
    $resp = App::service()->getTweets("$tag+exclude:retweets", $max);
    
    if (!empty($resp)) {
      
      foreach ($resp->results as $tw) {
        $search = array('%username',    '%id');
        $replac = array($tw->from_user, $tw->id_str);
        // User
        $profileUrl = App::conf('services.twitter.urls.profile');      
        $profileUrl = str_replace($search, $replac, $profileUrl);
        $user = App::view()->renderLink($profileUrl, "@{$tw->from_user}");
        // Date
        $statusUrl = App::conf('services.twitter.urls.status');
        $statusUrl = str_replace($search, $replac, $statusUrl);
        $date = App::view()->renderLink($statusUrl, App::utils()->getDateStr($tw->created_at));
        $text = App::view()->renderTweet($tw->text);
        // Add result
        $results[TYPE_TWEET][] = "[$user][$date] $text";
        if ($count++ === $max) break;
      }
    }        
  }
  
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
            $date = App::utils()->getDateStr($dateTime, true, true);
            // Using suffix to avoid key overriding
            $results[TYPE_EVENT][$dateTime . "#$count"] = "[$date] {$event->summary} - "
              . App::view()->renderLink(App::service()->getBitlyUrl($event->htmlLink));
            $count++;
          }
        }
      } else {
        App::log()->err("Unable to get events from $calendar");
      }
    }
    // Order by date time
    if (!empty($results[TYPE_EVENT])) {
      ksort($results[TYPE_EVENT]);
    }
  }
  
  // Total
  
  App::log()->debug("TOTAL: " . count($results) . " section/s have updates");
    
  /**
   * Render
   */
  
  $main = App::conf('app.title') . " ". date('d/m/Y');
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
  }
  
  // Title and credits
  
  App::output(
    "<header>" . PHP_EOL . 
      "<h1>" . $main . "</h1>" . PHP_EOL . 
    "</header>" . PHP_EOL
  );
  
  // Sections
  
  App::output("<section>" . PHP_EOL);
  foreach ($results as $type => $list) {
    // Default
    $title = App::conf("app.$type.title");
    $url = App::conf("app.$type.url");
    // Specific
    if ($type === TYPE_TWEET && empty($url)) {
        $url = App::conf('services.twitter.urls.search');
        $url = str_replace('%query', urlencode($tag), $url);
    }
    // Render
    App::output(
      "<article>"  . PHP_EOL . 
        "<h2>" . ($url ? App::view()->renderLink(App::service()->getBitlyUrl($url), $title) : $title) . "</h2>" . PHP_EOL . 
        App::view()->renderList($list). PHP_EOL . 
      "</article>" . PHP_EOL
    );
  }
  App::output("</section>" . PHP_EOL);
  
  // Footer
  
  $credits = App::conf('app.credits');
  
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
    "<footer>" . App::view()->renderList($credits) . "</footer>"
  );
  
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
    $to = App::conf('app.output.mail.to');
    $from = App::conf('app.output.mail.from');
    $headers = $from ? "From: $from\r\n" . "X-Mailer: php" : "";
    $append = App::conf('app.output.mail.append') ?: "";
    // Send e-mail
    App::log()->debug("Sending mail to $to...");
    $ok = mail($to, $title, App::output() . $append, $headers);
    // Success?
    if ($ok) {
      App::log()->debug("Message sent ok!");
    } else {
      App::log()->debug("Message delivery failed.");
    }
  }
  
} catch (Exception $e) {
  App::log()->err($e->getMessage());
  exit(1);
}

exit(0);