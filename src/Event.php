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
        $this->events = array_map(fn ($event_name) => $this->configureEvent($event_name), Loader::getClassesWithParent("Sohris\Event\Event\AbstractEvent"));
        //self::saveEventFile();
    }

    public function configureEvent($event_name)
    {   
        $event = new $event_name;
        $configs = EventUtils::getSavedConfigurationEvents($event_name);
        if ($configs)
            $event->reconfigure($configs);

        self::$events_configuration[$event_name] = $event->getConfiguration();

        return $event;
    }

    public function start()
    {
        array_map(fn ($event) => $event->start(), $this->events);
        Loop::addPeriodicTimer(10, fn () => $this->reconfigureEvents());
    }

    public function reconfigureEvents()
    {
        foreach (self::$events_configuration as $event_name => $config) {
            $configs = EventUtils::getSavedConfigurationEvents($event_name);
            if (!$configs) continue;

            $event = $this->getEventClass($event_name);
            if (!$event) continue;

            $event->reconfigure($configs);
            self::$events_configuration[$event_name] = $event->getConfiguration();
        }
        //self::saveEventFile();
    }

    public function getEventClass($event_name)
    {
        $filtered = array_filter($this->events, fn ($ev) => $ev instanceof $event_name);
        if (empty($filtered)) {
            return false;
        }
        return array_pop($filtered);
    }

    public static function saveEventFile()
    {
        $file = Server::getRootDir() . DIRECTORY_SEPARATOR . self::EVENT_FILE_NAME;

        if (!Utils::checkFileExists($file)) {
            touch($file);
        }
        file_put_contents($file, json_encode(self::$events_configuration));
    }
}
