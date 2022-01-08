<?php

namespace Sohris\Event;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Sohris\Core\Component\AbstractComponent;
use Sohris\Core\Loader;
use Sohris\Core\Logger;

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
        $this->loadEvents();
        self::$logger->debug(sizeof($this->events)." events to load!");
    }

    private function loadEvents()
    {
        $this->events = array_map(fn($event_name) => new $event_name, Loader::getClassesWithParent("Sohris\Event\Event\AbstractEvent"));
    }

    public function start()
    {
        array_map(fn($event) => $event->start(), $this->events);
    }
}
