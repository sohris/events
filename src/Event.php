<?php

namespace Sohris\Event;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Sohris\Core\AbstractComponent;
use Sohris\Core\Components\Logger;

final class Event extends AbstractComponent
{
    private $events = [];

    public static $logger;

    public function __construct()
    {
        self::$logger = new Logger("Events");
    }

    public function install()
    {
    
        $unloaded_events = Utils::getAllEvent();
        self::$logger->debug(sizeof($unloaded_events)." events to load!");

        foreach ($unloaded_events as $event) {
            $e = new $event();
            array_push($this->events, $e);
        }
    }


    public function start()
    {
        foreach($this->events as $event)
        {
            $event->start();
        }
    }
}
