<?php

namespace Sohris\Event\Commands;

use Cron\CronExpression;
use DateTime;
use React\EventLoop\Loop;
use Sohris\Core\Loader;
use Sohris\Core\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ExecuteGroup extends Command
{
    private static $events = [];

    private static $configure_cron = [];

    private static $info_section;
    private static $stats_section;
    private static $error_section;
    private static $debug_section;

    private static $debug;
    private static $log;
    private static $group_name = 'default';


    protected function configure(): void
    {
        $this
            ->setName("sohris:execute_group")
            ->setDescription('Execute a group of registred events')
            ->setHelp('This command execute an events that extends the Sohris\Event\Event\EventControl')
            ->addOption('single-thread', 's', NULL, 'Run events in Single Thread')
            ->addOption('debug', 'd', NULL, 'Run events in Single Thread')
            ->addOption('log-in-file', 'l', NULL, 'Run events in Single Thread')
            ->addArgument('group', InputArgument::REQUIRED, 'Event Group Name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }

        Loader::loadClasses();
        $group = trim(strtolower($input->getArgument("group")));
        $single_thread = $input->getOption("single-thread");
        self::$debug = $input->getOption("debug");
        self::$log = $input->getOption("log-in-file");

        self::$group_name = strtoupper($group);

        $events = [];

        foreach (Loader::getClassesWithParent("Sohris\Event\Event\EventControl") as $event) {

            $ev = new $event;
            if ($ev->group === $group)
                $events[] = $ev;
            else
                unset($ev);
        }

        self::$info_section = $output->section();
        self::$stats_section = $output->section();
        self::$debug_section = $output->section();
        self::$error_section = $output->section();

        self::$info_section->writeln([
            "===============Info==============",
            "Group: " . strtoupper($group),
            "Size: " . count($events),
            "Mode: " . $single_thread === true ? "SingleThread" : "MultiThread"
        ]);

        if ($single_thread === true) {
            $this->singleThread($events);
        } else {
            $this->multiThread($events);
        }




        Loop::run();
        return Command::SUCCESS;
    }

    private function singleThread(array $evs)
    {

        self::$stats_section->writeln("Configuration");
        $progress = new ProgressBar(self::$stats_section);
        $progress->setMaxSteps(count($evs));
        $progress->setFormat('%current:' . count($evs) . 's%/%max% [%bar%] %percent:1s%% %elapsed:3s%/%estimated:-3s% %message%');
        foreach ($evs as $ev) {
            $name = get_class($ev);
            $info = $ev->getInfo();
            //First Run
            $progress->setMessage("StartUp " . $name);
            $ev::firstRun();

            //Start Running
            if ($info['start_running']) {
                $progress->setMessage("Start Running " . $name);
            }

            //Current Running
            switch ($info['interval_type']) {
                case "Cron":
                    $cron = $info['interval_frequency'];
                    self::$configure_cron[] = [
                        "name" => $name,
                        "cron" => $cron
                    ];
                    break;
                case "Interval":
                    Loop::addPeriodicTimer($info['interval_frequency'], fn () => self::executeTask($name));
                    break;
            }
            $progress->advance();
        }
        Loop::addPeriodicTimer(1, function () {
            foreach (self::$configure_cron as $c) {
                $cron = str_replace("\\", "/", $c['cron']);
                $time = CronExpression::factory($cron);
                $to_run = $time->getNextRunDate();

                $now = new DateTime();
                $diff = $to_run->getTimestamp() - $now->getTimestamp();
                Loop::addTimer($diff, function () use ($c) {
                    self::executeTask($c['name']);
                    self::$configure_cron[] = $c;
                });
            }
            self::$configure_cron = [];
        });
    }

    private function multiThread(array $evs)
    {
        foreach ($evs as $ev) {
            if (!$ev) continue;
            $ev->on("start_event", fn () => self::log("DEBUG", get_class($ev) . " - start"));
            $ev->on("error", fn ($e) => self::log("ERROR", "Code: $e[errcode] - Message: $e[errmsg] - File: $e[errfile]($e[errline])"));
            $ev->on("finish_event", fn () => self::log("DEBUG", get_class($ev) . " - finish"));
            $ev->start();
        }
    }

    private static function executeTask($name)
    {
        self::log("DEBUG", "$name - start");
        try {
            \call_user_func($name . "::run");
        } catch (Throwable $e) {
            self::log("ERROR", "Code: " . $e->getCode() . " - Message: " . $e->getMessage() . " - File: " . $e->getFile() . "(" . $e->getLine() . ")");
        }
        self::log("DEBUG", "$name - finish");
    }

    private static function log($type, $message)
    {
        if (self::$debug === true) {
            $date = date("Y-m-d H:i:s");
            $type = strtoupper($type);
            self::$debug_section->write("[$date][$type] $message");
        }
        if (self::$log === true) {
            $log = new Logger("EVENT_GROUP_" . self::$group_name);
            $log->warning($message);
        }
    }
}
