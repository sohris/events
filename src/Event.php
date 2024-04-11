<?php

namespace Sohris\Event;

use React\EventLoop\Loop;
use Sohris\Core\ComponentControl;
use Sohris\Core\Loader;
use Sohris\Core\Logger;
use Sohris\Event\Event\EventControl;

final class Event extends ComponentControl
{
    const EVENT_FILE_NAME = ".event.json";
    private $events = [];

    public static $logger;

    private $file;

    public static $events_configuration = [];

    public function __construct()
    {
        self::$logger = new Logger("Events");

    }

    public function install()
    {
        $this->loadEvents();
        self::$logger->debug(sizeof($this->events) . " events to load!");        
    }

    private function loadEvents()
    {
        foreach(Loader::getClassesWithParent("Sohris\Event\Event\EventControl") as $event)
        {
            $key = sha1($event);
            $this->events[$key] = new $event;
        }
    }

    public function start()
    {
        array_walk($this->events, fn ($event) => !$event->not_running | $event->start());
    }

    public function getStats()
    {
        $stats = [];
        array_walk($this->events, function ($ev) use (&$stats){
            $ev_stats = $ev->getStats();
            $ev_stats['event'] = get_class($ev);
            $stats[] = $ev_stats;
        });
        return $stats;
    }

    public function getEvent($even_name)
    {   
        $key = sha1($even_name);
        if(!array_key_exists($key, $this->events)) return null;

        return $this->events[$key];
    }
    
}
