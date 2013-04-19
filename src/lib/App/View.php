<?php

class App_View 
{
  public function renderLink($url, $title = null)
  {
    return "<a href='$url' target='_blank'>" . ($title ?: $url) . "</a>";
  }

  public function renderList($list) 
  {
    $html = "<ul>" . PHP_EOL;
    foreach ($list as $title => $item) {
      if (is_array($item)) {
        $html .= "<li>" . $title . "</li>" . PHP_EOL;
        $html .= $this->renderList($item);
      } else {
        $html .= "<li>" . $item . "</li>" . PHP_EOL;
      }
    }
    $html .= "</ul>" . PHP_EOL;

    return $html;
  }
  
  public function renderArticle($content, $title = null, $url = null)
  {
    return 
      "<article>"  . PHP_EOL . 
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