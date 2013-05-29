<?php

class App_View 
{
  protected $_inlineStyle = array();
  
  public function getInlineStyle()
  {
    return $this->_inlineStyle;
  }
  
  public function setInlineStyle(array $inlineStyle)
  {
    $this->_inlineStyle = $inlineStyle;
  }
  
  public function renderTag($tag, $html, $attrs = array())
  {
    if (!isset($attrs['style'])) {
      $selectors = array(
        $tag,
        empty($attrs['class']) ? '*' : $tag . '.' . $attrs['class']
      );
      foreach ($selectors as $selector) {
        if (isset($this->_inlineStyle[$selector])) {
          $attrs['style'] = $this->_inlineStyle[$selector];
        }
      }
    }
    
    foreach ($attrs as $attr => $content) {
      $tag .= " $attr=\"$content\"";
    }
    
    return "<$tag>" . PHP_EOL . $html . PHP_EOL . "</$tag>" . PHP_EOL;
  }
  
  public function renderTitle($title, $h = 1)
  {
    return $this->renderTag("h$h", $title);
  }
  
  public function renderLink($url, $title = null, array $attrs = array())
  {
    return $this->renderTag('a', $title ?: $url, array_merge($attrs, array(
      'href' => $url,
      'target' => '_blank',
    )));
  }

  public function renderList($list, $depth = 0) 
  {
    $html = "";
    
    foreach ($list as $title => $item) {
      $html .= $this->renderTag('li', is_array($item) ? $title : $item, array(
        'class' => "depth$depth-item"
      )); 
      if (is_array($item)) {
        $html .= $this->renderList($item, $depth + 1);
      }
    }
    
    return $this->renderTag('ul', $html, array(
      'class' => "depth$depth"
    ));
  }
  
  public function renderSection($content, $title = null, $url = null, $class = 'default')
  {
    $html = 
      ($title ? $this->renderTitle($url ? $this->renderLink($url, $title, array("class" => "section-link")) : $title, 2) : "") . PHP_EOL . 
      (is_array($content) ? $this->renderList($content) : $content . PHP_EOL);
    
    return $this->renderTag('div', $html, array(
      'class' => $class
    ));
  }
  
  public function renderDate($date, $showDay = true, $showHour = false, $showWeekDay = false)
  {
    $date = is_a($date, 'DateTime') ? $date : new DateTime($date);
    $weekDay = date_format($date, 'D');
    $day = date_format($date, ($showDay ? 'd-m-Y' : '') . 
      ($showDay && $showHour ? ' ' : '') . ($showHour ? 'H:i' : ''));
    
    return ($showWeekDay ? App_Registry::utils()->t($weekDay) . ' ' : '') . $day;
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