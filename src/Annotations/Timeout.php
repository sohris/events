<?php

namespace Sohris\Event\Annotations;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class Timeout 
{

    /**
     * @var integer 
     */
    public $value = 60;

    private LoopInterface $loop;

    private $callable;


    private TimerInterface $timer;

    
    public function __construct($args)
    {
        $this->value = $args['value'];

        $this->loop = Loop::get();

    }

    public function configure(callable $callable)
    {
        $this->callable = $callable;
    }

    public function startTimeout()
    {
        $this->timer = $this->loop->addPeriodicTimer(1, fn() => \call_user_func($this->callable));
    }

    public function stopTimeout()
    {
        $this->loop->cancelTimer($this->timer);
    }


}