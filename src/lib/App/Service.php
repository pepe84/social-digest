<?php

class Service
{
  // Supported blog services
  const BLOG_WP = 'wordpress';
  const BLOG_BG = 'blogger';
  const BLOG_BS = 'blogspot';
  const BLOG_SN = 'statusnet';
  
  public function getRss($url, $tag, $type = null)
  {
    // Try to discover type
    if (empty($type)) {
      if (stripos($url, 'wordpress.com')) {
        $type = self::BLOG_WP;        
      } else if (stripos($url, 'blogger.com')) { 
        $type = self::BLOG_BG;        
      } else if (stripos($url, 'blogspot.com')) {
        $type = self::BLOG_BS;
      }
    }
    
    switch ($type) {
      case self::BLOG_WP:
        // Wordpress
        // http://codex.wordpress.org/WordPress_Feeds#Categories_and_Tags
        $uTag = urlencode($tag);
        $url = $url . "?feed=rss2&tag=$uTag";
        break;
      case self::BLOG_BG:
      case self::BLOG_BS:
        // Blogspot / Blogger (Google)
        // https://developers.google.com/blogger/docs/2.0/developers_guide_protocol#RetrievingWithQuery
        $uTag = urlencode($tag);
        $url = $url . "/feeds/posts/default/-/$uTag?alt=rss";
        break;
      case self::BLOG_SN:
        $sTag = str_replace('#', '', $tag);
        $url = $url . "/api/statusnet/tags/timeline/$sTag.rss";
        break;
      default:
        // Not implemented
        throw new InvalidArgumentException("Unable to read blog $url");
    }
    
    return Http::getRequest($url, Http::CONTENT_TYPE_XML);
  }

  public function getTweets($search, $max, $type = 'recent')
  {
    // Twitter API v1.0 (deprecated!)
    // https://dev.twitter.com/docs/api/1/get/search
    $query = Http::createQuery(array(
      'q'           => urlencode($search), // use "to:ECoordinacio"?
      'result_type' => $type,
      'count'       => $max,
    ));
    $url = "http://search.twitter.com/search.json" . $query;

    return Http::getRequest($url, Http::CONTENT_TYPE_JSON);
  }

  public function getGoogleCalendarEvents($cal, $startDate, $endDate)
  {
    // Google Calendar API v3
    // https://developers.google.com/google-apps/calendar/v3/reference/events/list
    $query = Http::createQuery(array(
      'key'           => Config::get('services.google.apiKey'),
      'timeMin'       => $startDate->format('Y-m-d\TH:i:s\Z'),  // RFC3339
      'timeMax'       => $endDate->format('Y-m-d\TH:i:s\Z'),    // RFC3339
      'singleEvents'  => 'true',      // required to use orderBy
      'orderBy'       => 'startTime', // ascending order
    ));
    $cal = urlencode($cal);
    $url = Config::get('services.google.calendar.endpoint') . "/calendars/$cal/events" . $query;
    
    return Http::getRequest($url, Http::CONTENT_TYPE_JSON);
  }

  public function getBitlyUrl($url)
  {
    // Use bitly service to minimize and track url
    // http://dev.bitly.com/links.html#v3_shorten
    $query = Http::createQuery(array(
      'access_token'  => Config::get('services.bitly.apiKey'),
      'longUrl'       => urlencode($url)
    ));
    
    $api = Config::get('services.bitly.endpoint') . '/shorten' . $query;
    $result = Http::getRequest($api, Http::CONTENT_TYPE_JSON);
    
    if (!empty($result->data) && !empty($result->data->url)) {
      $url = $result->data->url;
    }
    
    return $url;
  }

}