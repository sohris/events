<?php

namespace Sohris\Event\Event;

use React\EventLoop\LoopInterface;

interface IEvent
{

    public static function run();
}