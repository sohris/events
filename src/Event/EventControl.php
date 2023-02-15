<?php

namespace Sohris\Event\Event;

use Exception;
use React\EventLoop\Loop;
use Sohris\Core\Tools\Worker\Worker;
use Sohris\Core\Utils;
use Sohris\Event\Utils as EventUtils;

abstract class EventControl
{

    private $configuration;

    private $control;

    private $start_running = false;

    private $active = true;

    private $stats = [
        "time" => 0,
        "total_run" => 0,
        "memory" => 0,
        "start" => 0,
        "restart" => 0,
        "last_run" => 0,
        "frequency" => 0
    ];

    /**
     * @var Worker
     */
    private $worker;

    public function __construct()
    {

        $this->configuration = EventUtils::loadAnnotationsOfClass($this);

        $annotations = $this->configuration['annotations'];
        foreach ($annotations as $annotation) {
            if (get_class($annotation) == "Sohris\Event\Annotations\Time") {
                $this->control = $annotation;
            } else if (get_class($annotation) == "Sohris\Event\Annotations\StartRunning") {
                $this->start_running = true;
            }
        }
        $this->worker = new Worker;
        $this->configureWorker();
    }

    private function configureWorker()
    {
        $class_name = get_class($this);
        $this->worker->callOnFirst(static fn () => \call_user_func($class_name. "::firstRun"));

        if($this->start_running)
            $this->worker->callOnFirst(static fn($emitter) => self::runEvent($emitter, $class_name));

        $func = null;
        switch ($this->control->getType()) {
            case "Cron":
                $func = "callCronFunction";
                break;
            case "Interval":
                $func = "callFunction";
                break;
            default:
        }

        $this->worker->{$func}(static fn($emitter) => self::runEvent($emitter,$class_name), $this->control->getTime());

        $this->worker->callFunction(static function ($emitter) {
            $emitter('update_stats', [
                "memory" => memory_get_peak_usage()
            ]);
        }, 60);

        $this->worker->on("run_update", function ($response) {
            $this->stats['last_run'] = $response['last_run'];
            $this->stats['time'] += $response['time'];
            $this->stats['total_run']+=1;
        });

        $this->worker->on("update_stats", function ($response) {
            $this->stats['memory'] = $response['memory'];
        });
    }

    private static function runEvent($emitter, $class_name)
    {
        $start = Utils::microtimeFloat();
        \call_user_func($class_name. "::run");
        $emitter('run_update', [
            "time" => (Utils::microtimeFloat() - $start),
            "last_run" => time()
        ]);
    }

    public function reconfigure(array $configuration)
    {

        if (array_key_exists('enable', $configuration)) {
            $a = $this->active;
            $this->active = $configuration['enable'] == true ? true : false;
            if (!$a && $configuration['enable'])
                $this->control->start();

            if ($a && !$configuration['enable'])
                $this->control->stop();
        }
        if (array_key_exists('control', $configuration)) {
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
        $this->worker->run();
    }

    public function stop()
    {
        $this->worker->stop();
    }

    public function restart()
    {
        $this->stats['restart'] = time();
        $this->worker->restart();
    }

    public function getStats()
    {
        $uptime = time() - $this->stats['start'];
        return [
            "uptime" => $uptime,
            "memory" => $this->stats['memory'],
            "total_time_exec" => $this->stats['time'],
            "total_run" => $this->stats['total_run'],
            "avg_time" => $this->stats['total_run'] > 0 ?round($this->stats['time'] / $this->stats['total_run'], 3): 0,
            "last_run" => $this->stats['last_run'],
            "restart" => $this->stats['restart'],
            "frequency" => $this->stats['frequency'],
            "last_error" => $this->worker->getLastError()
        ];
    }

    abstract public static function run();
    abstract public static function firstRun();
}
