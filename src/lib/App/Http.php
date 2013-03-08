<?php

class Http
{
  const CONTENT_TYPE_XML  = 'xml';
  const CONTENT_TYPE_JSON = 'json';
  
  static public function createQuery($params) 
  {
    array_walk($params, function(&$v, $k){
      $v = "$k=" . urlencode($v);
    });
    
    return '?' . implode('&', $params);
  }
  
  static public function getRequest($url, $type) 
  {
    $content = @file_get_contents($url);

    if (!empty($content)) {
      switch ($type) {
        case self::CONTENT_TYPE_XML:
          return new SimpleXmlElement($content);
        case self::CONTENT_TYPE_JSON:
          return json_decode($content);
      }
    }

    return null;
  }  
}