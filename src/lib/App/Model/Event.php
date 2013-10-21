<?php

class App_Model_Event
{
  const TYPE_ICAL             = 0;
  const TYPE_GOOGLE_CALENDAR  = 1;
  
  public $summary;
  public $description;
  public $location;
  public $startDate;
  public $endDate;
  
  public $allDay;
  public $link;
  
  static public function factory($event)
  {
    $model = new self();
    
    if ($event instanceof \SG_iCal_VEvent) {
      // iCalendar
      $model->summary     = $event->getSummary();
      $model->description = $event->getDescription();
      $model->location    = $event->getLocation();
      $model->allDay      = $event->isWholeDay() || $event->getDuration() > 86400;
      
      $model->startDate = new DateTime('@' . $event->getStart());
      $model->endDate = new DateTime('@' . $event->getEnd());
      
      // Fix time zone after importing timestamp
      $timeZone = new DateTimeZone(date_default_timezone_get());
      $model->endDate->setTimezone($timeZone);
      $model->startDate->setTimezone($timeZone);
      
    } else {
      // Google Calendar      
      $model->summary     = $event->summary;
      $model->description = isset($event->description) ? $event->description : "";
      $model->location    = isset($event->location) ? $event->location : "";
      $model->allDay      = isset($event->start->date);
      $model->link        = $event->htmlLink;
      
      $startTime = isset($event->start->dateTime) ? $event->start->dateTime : $event->start->date;
      $model->startDate = new DateTime($startTime);
      
      $endTime = isset($event->end->dateTime) ? $event->end->dateTime : $event->end->date;
      $model->endDate = new DateTime($endTime);
    }
    
    return $model;
  }
}