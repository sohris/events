<?php

namespace Sohris\Event\Annotations;

use Cron\CronExpression;
use DateTime;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class Time
{

    /**
     * @Enum({"Cron","Interval"})
     */
    public $type = "Interval";

    private $running = false;
    private $configuration = [
        "time" => "",
        "type" => ""
    ];

    /**
     * @var CronExpression|int
     */
    public $time = 60;

    private \DateTime $last_run;

    private \DateTime $next_run;

    private TimerInterface $timer;

    private LoopInterface $loop;
    private $callable;

    public function __construct($args)
    {
        $this->setConfiguration($args);
    }

    public function setConfiguration($args)
    {
        if ($this->configuration['type'] == $args['type'] && $this->configuration['time'] == $args['time'])
            return;

        $this->type = $args['type'];

        $this->time = $args['time'];

        if ($this->type == 'Cron') {
            $cron = str_replace("\\", "/", $args['time']);
            $this->time = CronExpression::factory($cron);
        }

        $this->configuration = $args;
        if ($this->running)
            $this->restart();
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function start()
    {
        echo "S" . PHP_EOL;
        if ($this->running) {
            return;
        }
        if ($this->type == "Interval") {
            $this->timer = $this->loop->addPeriodicTimer($this->time, fn () => $this->runCallable());
        } else if ($this->type == "Cron") {
            $this->configureCron();
        }
        echo "2" . PHP_EOL;
        $this->running = true;
    }

    public function stop()
    {
        echo "St" . PHP_EOL;
        if(!$this->running)
            return;
        if (($this->timer)) {
            $this->loop->cancelTimer($this->timer);
        }
        $this->running = false;
    }

    public function restart()
    {
        $this->stop();
        $this->start();
    }

    public function configureTimer(callable $callable)
    {
        $this->loop = Loop::get();
        $this->callable = $callable;
    }

    private function configureCron()
    {
        $now = new DateTime();
        $to_run = $this->time->getNextRunDate();

        $diff = $to_run->getTimestamp() - $now->getTimestamp();
        $this->timer = $this->loop->addTimer($diff, function () {
            $this->runCallable();
            $this->configureCron();
        });
    }

    private function runCallable()
    {
        $this->configureRunningTimer();
        \call_user_func($this->callable);
    }

    private function configureRunningTimer()
    {
        if (isset($this->next_run))
            $this->last_run = $this->next_run;

        if ($this->type == "Cron")
            $this->next_run = new \DateTime($this->time->getNextRunDate()->format("Y-m-d H:i:s"));
        else {
            $interval = new \DateInterval("PT" . $this->time . "S");
            $this->next_run = new \DateTime();
            $this->next_run->add($interval);
        }
    }

    public function getNextRun()
    {
        return $this->next_run;
    }

    public function getPreviousRun()
    {
        return $this->last_run;
    }
}
