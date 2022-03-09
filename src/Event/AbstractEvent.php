<?php

namespace Sohris\Event\Event;

use parallel\Channel;
use parallel\Events;
use parallel\Runtime;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Sohris\Core\Server;
use Sohris\Core\Utils;
use Sohris\Event\Event;
use Sohris\Event\Utils as EventUtils;

abstract class AbstractEvent implements IEvent
{

    public $enable = true;

    /**
     * Status of event, can be "waiting"/"running"/"disabled"
     * 
     * @var string 
     */
    public $status = "never_running";

    private $configuration;

    private $control;

    private $active = true;

    private $channel_name = "";

    private Channel $channel;

    private Events $events;

    private Runtime $runtime;

    private TimerInterface $timer_check_events;

    private LoopInterface $loop;


    private $startRunning = false;

    public function __construct()
    {
        $this->loop = Loop::get();

        $bootstrap = Server::getRootDir() . DIRECTORY_SEPARATOR . "bootstrap.php";
        
        $this->configuration = EventUtils::loadAnnotationsOfClass($this);

        $annotations = $this->configuration['annotations'];
        foreach ($annotations as $annotation) {
            if (get_class($annotation) == "Sohris\Event\Annotations\Time") {
                $this->control = $annotation;
            } else if (get_class($annotation) == "Sohris\Event\Annotations\Timeout") {
                $this->timeoutControl = $annotation;
            } else if (get_class($annotation) == "Sohris\Event\Annotations\StartRunning") {
                $this->startRunning = true;
            }
        }

        $this->control->configureTimer(fn () => $this->threadRunning());

        $this->channel_name = "threads_control" . $this->configuration['class']->getName();

        $this->channel = Channel::make($this->channel_name, Channel::Infinite);

        $this->events = new Events;
        $this->events->addChannel($this->channel);
        $this->events->setBlocking(false);

        $this->runtime = new Runtime($bootstrap);
    }
    
    public function reconfigure(array $configuration)
    {

        if(array_key_exists('enable',$configuration)){
            $a = $this->active;
            $this->active = $configuration['enable'] == true ? true: false;
            if(!$a && $configuration['enable'])
                $this->control->start();

            if($a && !$configuration['enable'])
                $this->control->stop();

        }
        if(array_key_exists('control', $configuration))
        {
            $this->control->setConfiguration($configuration['control']);
        }    
    }

    public function getConfiguration()
    {
        return [
            'enable' => $this->active,
            'control' => $this->control->getConfiguration()
        ];
    }

    public function start()
    {
        if(!$this->active)
            return;
        $this->control->start();
        if ($this->startRunning ) {
            $this->logger("Start Running");
            $this->threadRunning();
        }
    }

    public function stop()
    {
        $this->control->stop();
        $this->status = 'disabled';
    }

    private function threadRunning()
    {
        if ($this->status != "waiting" && $this->status != "never_running" ) {
            return;
        }

        $class = get_class($this);
        $method = "run";


        $this->runtime->run(function ($channel) use ($class, $method) {
            $start = Utils::microtimeFloat();
            $channel->send(["STATUS" => "RUNNING"]);
            \call_user_func($class . "::" . $method);
            $end = Utils::microtimeFloat();
            $channel->send(["STATUS" => "FINISH", "TIME" => round(($end - $start), 3)]);
        }, [$this->channel]);
        $this->timer_check_events = $this->loop->addPeriodicTimer(0.5, fn () => $this->checkEvent());
    }

    private function checkEvent()
    {
        $event = $this->events->poll();

        if ($event && $event->source == $this->channel_name) {
            $this->events->addChannel($this->channel);
            switch ($event->value['STATUS']) {
                case "FINISH":
                    $time = $event->value["TIME"];
                    $message = "Finish event (" . $time . ")";
                    $this->logger($message);
                    $this->loop->cancelTimer($this->timer_check_events);
                    $this->status = 'waiting';
                    break;
                case "RUNNING":
                    $this->logger("Start event");
                    $this->status = 'running';
                    break;
            }
        }
    }

    private function logger($message)
    {
        $m = "[\"" . $this->configuration['class']->getName() . "\"] - $message";

        Event::$logger->debug($m);
    }
}
