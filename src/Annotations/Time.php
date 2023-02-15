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


    /**
     * @var CronExpression|int
     */
    public $time = 60;


    public function __construct($args)
    {
        $this->setConfiguration($args);
    }

    public function setConfiguration($args)
    {
        $this->type = $args['type'];

        $this->time = $args['time'];
    }

    public function getType()
    {
        return $this->type;
    }

    public function getTime()
    {
        return $this->time;
    }
}
