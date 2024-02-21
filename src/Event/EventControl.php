<?php

namespace Sohris\Event\Event;

use Cron\CronExpression;
use Exception;
use Sohris\Core\Logger;
use Sohris\Core\Tools\Worker\Worker;
use Sohris\Core\Utils;
use Sohris\Event\Utils as EventUtils;
use Throwable;

abstract class EventControl
{
    const TIME_TYPES = ['Cron', 'Interval'];

    private $configuration;

    private $control;

    private $start_running = false;
    private $not_running = false;

    private $frequency = 0;

    private $time_type = '';
    private $logger;

    private $stats = [
        "time" => 0,
        "total_run" => 0,
        "memory" => 0,
        "start" => 0,
        "restart" => 0,
        "restart_count" => 0,
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

        $this->stats['start'] = time();
        $annotations = $this->configuration['annotations'];
        foreach ($annotations as $annotation) {
            if (get_class($annotation) == "Sohris\Event\Annotations\Time") {
                $this->control = $annotation;
            } else if (get_class($annotation) == "Sohris\Event\Annotations\StartRunning") {
                $this->start_running = true;
            } else if (get_class($annotation) == "Sohris\Event\Annotations\NotRunning") {
                $this->not_running = true;
            }
        }
        $this->frequency = $this->control->getTime();
        $this->time_type = $this->control->getType();
        $this->worker = new Worker;
        $this->worker->stayAlive();
        $this->configureWorker();
        $this->logger = new Logger("Events");
    }

    private function configureWorker()
    {
        if($this->not_running) return;
        $class_name = get_class($this);
        $this->worker->callOnFirst(static fn ($emitter) => self::runEvent($emitter, $class_name, "firstRun"));

        if ($this->start_running)
            $this->worker->callOnFirst(static fn ($emitter) => self::runEvent($emitter, $class_name, "run"));

        $func = null;
        switch ($this->time_type) {
            case "Cron":
                $func = "callCronFunction";
                break;
            case "Interval":
                $func = "callFunction";
                break;
            default:
        }

        $this->worker->{$func}(static fn ($emitter) => self::runEvent($emitter, $class_name, "run"), $this->frequency);

        $this->worker->callFunction(static function ($emitter) {
            $emitter('update_stats', [
                "memory" => memory_get_peak_usage()
            ]);
        }, 60);

        $this->worker->on("run_update", function ($response) {
            $this->stats['last_run'] = $response['last_run'];
            $this->stats['time'] += $response['time'];
            $this->stats['total_run'] += 1;
        });

        $this->worker->on("update_stats", function ($response) {
            $this->stats['memory'] = $response['memory'];
        });

        $this->worker->on("restart", function ($response) use ($class_name) {
            $this->stats['restart'] = time();
            $this->stats['restart_count']++;
            $this->logger->critical("Restarting $class_name!", $response);
        });
    }

    private static function runEvent($emitter, $class_name, $function)
    {
        try {
            $emitter('start_event', []);
            $start = Utils::microtimeFloat();
            \call_user_func($class_name . "::" . $function);
            $emitter('run_update', [
                "time" => (Utils::microtimeFloat() - $start),
                "last_run" => time()
            ]);
            $emitter('finish_event', []);
        } catch (Throwable $e) {
            $emitter('error', ['errmsg' => $e->getMessage(), 'errcode' => $e->getCode(), 'trace' => $e->getTrace()]);
            $logger = new Logger("Events");
            $logger->critical("[$class_name] - " . $e->getMessage() . " - " . $e->getFile() . " (" . $e->getLine() . ")");
        }
    }

    public function start()
    {
        if($this->not_running) return;
        $this->stats['start'] = time();
        $this->worker->run();
    }

    public function stop()
    {
        if($this->not_running) return;
        $this->worker->stop();
    }

    public function restart()
    {
        if($this->not_running) return;
        $this->stats['restart'] = time();

        $this->worker->kill();
        $this->worker = new Worker;
        $this->worker->stayAlive();
        $this->configureWorker();
        $this->worker->run();
    }

    public function getStats()
    {
        $uptime = time() - $this->stats['start'];
        return [
            "uptime" => $uptime,
            "memory" => $this->stats['memory'],
            "total_time_exec" => $this->stats['time'],
            "total_run" => $this->stats['total_run'],
            "avg_time" => $this->stats['total_run'] > 0 ? round($this->stats['time'] / $this->stats['total_run'], 3) : 0,
            "last_run" => $this->stats['last_run'],
            "restart" => $this->stats['restart'],
            "frequency" => $this->stats['frequency'],
            "last_error" => $this->worker->getLastError()
        ];
    }

    public function setFrequency($frequency)
    {
        switch ($this->time_type) {
            case "Cron":
                if (!CronExpression::isValidExpression($frequency))
                    throw new Exception("INVALID_CRON_EXPRESSION");
                break;
            case "Interval":
                if (!is_int($frequency) && !is_float($frequency))
                    throw new Exception("INVALID_FREQUENCY_FOR_INTERVAL");
                break;
        }

        $this->frequency = $frequency;
    }

    public function setTimeType($time_type)
    {

        if (!in_array($time_type, self::TIME_TYPES))
            throw new Exception("INVALID_TIME_TYPE");

        $this->time_type = $time_type;
    }

    public function getInfo(): array
    {
        return [
            "name" => get_class($this),
            "interval_type" => $this->control->getType(),
            "interval_frequency" => $this->control->getTime()
        ];
    }

    abstract public static function run();
    abstract public static function firstRun();
}
