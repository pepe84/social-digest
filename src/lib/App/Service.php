<?php

class App_Service
{
  // Supported feed services
  const FEED_WP = 'wordpress';
  const FEED_BG = 'blogger';
  const FEED_BS = 'blogspot';
  const FEED_SN = 'statusnet';
  const FEED_DL = 'delicious';
  const FEED_JL = 'joomla';
  
  public function getRegexs()
  {
    static $regexs;
     
    if (empty($regexs)) {
      // Using root to avoid overriding custom feed urls
      $root = "/^https{0,1}:\/\/#dom#\/{0,1}$/i";
      // Custom types
      $regexs = array(
        self::FEED_WP => str_replace('#dom#', '(\w+).wordpress.com', $root),
        self::FEED_BG => str_replace('#dom#', '(\w+).blogger.com', $root),
        self::FEED_BS => str_replace('#dom#', '(\w+).blogspot.com', $root),
        self::FEED_DL => str_replace('#dom#', 'delicious.com\/(\w+)', $root),
      );
    }
      
    return $regexs;
  }
  
  public function getRss($url, $tag = null, $type = null, $count = 100)
  {
    // Try to discover type
    if (empty($type)) {
      foreach ($this->getRegexs() as $service => $regex) {
        if (preg_match($regex, $url)) {
          $type = $service;
          break;
        }
      }
    }
    
    // Build RSS url
    switch ($type) {
      case self::FEED_WP:
        // Wordpress
        // http://codex.wordpress.org/WordPress_Feeds#Categories_and_Tags
        $url .= "?feed=rss2" . ($tag ? "&tag=" . urlencode($tag) : "");
        break;
      case self::FEED_BG:
      case self::FEED_BS:
        // Blogspot / Blogger (Google)
        // https://developers.google.com/blogger/docs/2.0/developers_guide_protocol#RetrievingWithQuery
        $url .= "/feeds/posts/default" . ($tag ? "/-/" . urlencode($tag) : "") . "?alt=rss";
        break;
      case self::FEED_SN:
        // Statusnet
        $url .= "/api" . ($tag ? "/statusnet/tags/timeline/" . str_replace('#', '', $tag) . ".rss" 
          : "/statuses/public_timeline.rss");
        break;
      case self::FEED_DL:
        // Delicious
        // https://delicious.com/developers/rssurls
        $user = end((explode('/', $url)));
        $url = "http://feeds.delicious.com/v2/rss/$user" . ($tag ? "/tag/" . urlencode($tag) : "") . "?count=$count";
        break;
      case self::FEED_JL:
        # TODO Available tag support?
        $url .= "/index.php?format=feed&type=rss";
      default:
        // Do nothing, url should already be a feed
    }
    
    return App_Http::getRequest($url, App_Http::CONTENT_TYPE_XML);
  }
  
  public function getTweets($search, $max, $type = 'recent')
  {
    // Twitter API v1.0 (deprecated!)
    // https://dev.twitter.com/docs/api/1/get/search
    $query = App_Http::createQuery(array(
      'q'           => $search, // use "to:ECoordinacio"?
      'result_type' => $type,
      'count'       => $max,
    ));
    $url = "http://search.twitter.com/search.json" . $query;

    return App_Http::getRequest($url, App_Http::CONTENT_TYPE_JSON);
  }

  public function getGoogleCalendarEvents($cal, $startDate, $endDate)
  {
    // Google Calendar API v3
    // https://developers.google.com/google-apps/calendar/v3/reference/events/list
    $query = App_Http::createQuery(array(
      'key'           => App_Config::get('services.google.apiKey'),
      'timeMin'       => $startDate->format('Y-m-d\TH:i:s\Z'),  // RFC3339
      'timeMax'       => $endDate->format('Y-m-d\TH:i:s\Z'),    // RFC3339
      'singleEvents'  => 'true',      // required to use orderBy
      'orderBy'       => 'startTime', // ascending order
    ));
    $cal = urlencode($cal);
    $url = App_Config::get('services.google.calendar.endpoint') . "/calendars/$cal/events" . $query;
    
    return App_Http::getRequest($url, App_Http::CONTENT_TYPE_JSON);
  }

  public function getBitlyUrl($url)
  {
    // Use bitly service to minimize and track url
    // http://dev.bitly.com/links.html#v3_shorten
    $query = App_Http::createQuery(array(
      'access_token'  => App_Config::get('services.bitly.apiKey'),
      'longUrl'       => $url
    ));
    
    $api = App_Config::get('services.bitly.endpoint') . '/shorten' . $query;
    $result = App_Http::getRequest($api, App_Http::CONTENT_TYPE_JSON);
    
    if (!empty($result->data) && !empty($result->data->url)) {
      $url = $result->data->url;
    }
    
    return $url;
  }

}