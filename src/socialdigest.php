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
    $resp = App::service()->getTweets($tag, $max);
    
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
        // Add result
        $results[TYPE_TWEET][] = "[$user][$date] {$tw->text}";
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
  
  // TODO
  // 0) Transform Tweet urls
  // 1) Upload a new post to Wordpress
  // 2) Configure Wordpress to send post by mail
  // 3) Post events on Twitter
  
  /**
   * Render
   */
  
  // Title
  App::output("<h1>" . App::conf('app.title') . " ". date('d/m/Y') . "</h1>" . PHP_EOL);
  
  // Credits
  $credits = App::conf('app.credits');
  array_walk($credits, function(&$val, $key) {
    $val = $key . ": " . App::view()->renderLink($val);
  });
  App::output(App::view()->renderList($credits));
  
  // Sections
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
      "<h2>" . ($url ? App::view()->renderLink($url, $title) : $title) . "</h2>" . PHP_EOL . 
      App::view()->renderList($list)
    );
  }
  
} catch (Exception $e) {
  App::log()->err($e->getMessage());
  exit(1);
}

exit(0);