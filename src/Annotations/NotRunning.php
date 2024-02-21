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
class NotRunning 
{

    /**
     * @var boolean
     */
    public $value = true;
}