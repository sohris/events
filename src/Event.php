<?php

namespace Sohris\Event;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Sohris\Core\Component\AbstractComponent;
use Sohris\Core\Loader;
use Sohris\Core\Logger;
use Sohris\Core\Server;
use Sohris\Core\Utils;
use Sohris\Event\Utils as EventUtils;

final class Event extends AbstractComponent
{
    const EVENT_FILE_NAME = ".event.json";
    private $events = [];

    public static $logger;

    private $file;

    public static $events_configuration = [];

    public function __construct()
    {
        self::$logger = new Logger("Events");
        $this->file = Utils::getConfigFiles('system')['statistics_file_events'];
    }

    public function install()
    {
        $this->loadEvents();
        self::$logger->debug(sizeof($this->events) . " events to load!");
        Loop::addPeriodicTimer(10, fn() => $this->getStats());
        
    }

    private function loadEvents()
    {
        $this->events = array_map(fn ($event_name) => new $event_name, Loader::getClassesWithParent("Sohris\Event\Event\EventControl"));
    }

    public function start()
    {
        array_map(fn ($event) => $event->start(), $this->events);
    }

    public function getStats()
    {
        $stats = [];
        array_walk($this->events, function ($ev) use (&$stats){
            $ev_stats = $ev->getStats();
            $ev_stats['event'] = get_class($ev);
            $stats[] = $ev_stats;
        });

        file_put_contents($this->file, json_encode($stats));
    }
}
