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

    public $time = 60;

    private \DateTime $last_run;

    private \DateTime $next_run;

    private TimerInterface $timer;

    private LoopInterface $loop;
    private $callable;

    public function __construct($args)
    {
        $this->type = $args['type'];

        $this->time = $args['time'];

        if($this->type == 'Cron')
        {
            $cron =str_replace("\\" , "/",$args['time']);
            echo $cron . PHP_EOL;
            $this->time = CronExpression::factory($cron);
        }

    }

    public function start()
    {

        if($this->type == "Interval")
        {
            $this->timer = $this->loop->addPeriodicTimer($this->time,fn () => $this->runCallable());

        }else if($this->type == "Cron")
        {
            $this->configureCron();
        }

    }

    public function stop()
    {
        if($this->timer)
        {
            $this->loop->cancelTimer($this->timer);
        }
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
        $this->timer = $this->loop->addTimer($diff,function () {
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
        if(isset($this->next_run))
            $this->last_run = $this->next_run;

        if($this->type == "Cron")
            $this->next_run = new \DateTime($this->time->getNextRunDate()->format("Y-m-d H:i:s"));
        else
        {
            $interval = new \DateInterval("PT" . $this->time . "S");
            $this->next_run = new \DateTime();
            $this->next_run->add($interval);
        }
        
    }

    public function getNextRun(){
        return $this->next_run;
    }

    public function getPreviousRun()
    {
        return $this->last_run;
    }
}