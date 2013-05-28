<?php

class App_View 
{
  public function renderDate($date, $showDay = true, $showHour = false, $showWeekDay = false)
  {
    $date = is_a($date, 'DateTime') ? $date : new DateTime($date);
    $weekDay = date_format($date, 'D');
    $day = date_format($date, ($showDay ? 'd-m-Y' : '') . 
      ($showDay && $showHour ? ' ' : '') . ($showHour ? 'H:i' : ''));
    
    return ($showWeekDay ? App_Registry::utils()->t($weekDay) . ' ' : '') . $day;
  }
  
  public function renderLink($url, $title = null)
  {
    return "<a href='$url' target='_blank'>" . ($title ?: $url) . "</a>";
  }

  public function renderList($list, $depth = 0) 
  {
    $html = "<ul class=\"depth$depth\">" . PHP_EOL;
    
    foreach ($list as $title => $item) {
      $html .= "<li class=\"depth$depth-item\">" . 
                  (is_array($item) ? $title : $item) .
               "</li>" . PHP_EOL;
      if (is_array($item)) {
        $html .= $this->renderList($item, $depth + 1);
      }
    }
    $html .= "</ul>" . PHP_EOL;

    return $html;
  }
  
  public function renderArticle($content, $title = null, $url = null, $class = 'default')
  {
    return 
      "<article class=\"{$class}\">"  . PHP_EOL . 
        ($title ? "<h2>" . ($url ? $this->renderLink($url, $title) : $title) . "</h2>" : "") . PHP_EOL . 
        (is_array($content) ? $this->renderList($content) : $content . PHP_EOL) .
      "</article>" . PHP_EOL;
  }
  
  /**
   * @see http://www.simonwhatley.co.uk/parsing-twitter-usernames-hashtags-and-urls-with-javascript
   */
  public function renderTweet($tweet)
  {
    // Instance for callbacks
    $me = $this;
    
    // Replament patterns
    $replacements = array(
      // Replace links
      '/([a-z]+:\/\/){0,1}[a-z0-9-_]+\.[a-z0-9-_:%&~\?\/.=]+/i' => function($m) use ($me) {
        return $me->renderLink($m[0]);
      },
      // Replace mentions
      '/[@]+[\wáéíóúàèìòùäëïöüñ]+/i' => function($m) use ($me) {
        $username = substr($m[0], 1);
        return $me->renderLink("http://twitter.com/$username", $m[0]);
      },
      // Replace hashtags
      '/[#]+[\wáéíóúàèìòùäëïöüñ]+/i' => function($m) use ($me) {
        $q = App_Http::createQuery(array('q' => $m[0]));
        return $me->renderLink("http://twitter.com/search$q", $m[0]);
      },
    );
    
    foreach ($replacements as $pattern => $callback) {
       $tweet = preg_replace_callback($pattern, $callback, $tweet);
    }
    
    return $tweet;
  }
}