<?php

class App_Utils 
{
  public function getDateAdd($date, $str)
  {
    $clone = clone $date;
    $clone->add(date_interval_create_from_date_string($str));
    
    return $clone;
  }
  
  public function getDateSub($date, $str)
  {
    $clone = clone $date;
    $clone->sub(date_interval_create_from_date_string($str));
    
    return $clone;
  }
  
  public function getDateStr($date, $showDay = true, $showHour = false, $showWeekDay = false)
  {
    $date = is_a($date, 'DateTime') ? $date : new DateTime($date);
    $weekDay = date_format($date, 'D');
    $day = date_format($date, ($showDay ? 'd-m-Y' : '') . 
      ($showDay && $showHour ? ' ' : '') . ($showHour ? 'H:i' : ''));
    
    return ($showWeekDay ? $this->t($weekDay) . ' ' : '') . $day;
  }
  
  public function getArrayValue($array, $key, $default = null)
  {
    return isset($array[$key]) ? $array[$key] : $default;
  }
  
  public function t($str) 
  {
    $config = App_Config::get("translations");
    return isset($config[$str]) ? $config[$str] : $str;
  }
}