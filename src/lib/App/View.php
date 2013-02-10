<?php

class View 
{
  public function renderLink($url, $title = null)
  {
    return "<a href='$url' target='_blank'>" . ($title ?: $url) . "</a>";
  }

  public function renderList($list) 
  {
    $html = "<ul>" . PHP_EOL;
    foreach ($list as $item) {
      $html .= "<li>" . $item . "</li>" . PHP_EOL;
    }
    $html .= "</ul>" . PHP_EOL;

    return $html;
  }

}