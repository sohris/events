<?php

namespace Sohris\Event\Commands;

use Cron\CronExpression;
use DateTime;
use React\EventLoop\Loop;
use Sohris\Core\Loader;
use Sohris\Core\Logger;
use Sohris\Core\Server;
use Sohris\Core\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ExecuteGroup extends Command
{
    private static $configure_cron = [];

    private static Logger $logger;
    private static $group_name = 'default';

    protected function configure(): void
    {
        $this
            ->setName("sohris:execute_group")
            ->setDescription('Execute a group of registred events')
            ->setHelp('This command execute an events that extends the Sohris\Event\Event\EventControl')
            ->addOption('single-thread', 's', NULL, 'Run events in Single Thread')
            ->addArgument('group', InputArgument::REQUIRED, 'Event Group Name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }

        Server::setOutput($output);
        Loader::loadClasses();
        $group = trim(strtolower($input->getArgument("group")));
        $single_thread = $input->getOption("single-thread");
        self::$group_name = strtoupper($group);
        $events = [];

        self::$logger = new Logger('EVENT_GROUP_' . self::$group_name);

        foreach (Loader::getClassesWithParent("Sohris\Event\Event\EventControl") as $event) {
            $ev = new $event;
            if ($ev->group === $group)
                $events[] = $ev;
            else
                unset($ev);
        }
        self::$logger->info("Startup!");
        self::$logger->info(count($events) . " Events Registred");
        self::$logger->info("Mode " . ($single_thread === true ? "SingleThread" : "MultiThread"));

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

        self::$logger->info("Configuring Events");
        foreach ($evs as $ev) {
            $name = get_class($ev);
            $info = $ev->getInfo();
            //First Run            
            self::$logger->debug("StartUp $name");
            self::$logger->debug("$name Type $info[interval_type] Config $info[interval_frequency] StartRunning " . ($info['start_running'] ? "Yes" : "No"));

            $ev::firstRun();

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
            $ev->on("start_event", fn () => self::$logger->debug(get_class($ev) . " - Start!"));
            $ev->on("error", fn ($e) => self::$logger->error("Code: $e[errcode] - Message: $e[errmsg] - File: $e[errfile]($e[errline])"));
            $ev->on("finish_event", fn () => self::$logger->debug(get_class($ev) . " - Finish!"));
            $ev->start();
        }
    }

    private static function executeTask($name)
    {
        self::$logger->debug(self::$group_name . " - " . $name . " - Start!");
        try {
            $start = Utils::microtimeFloat();
            \call_user_func($name . "::run");
        } catch (Throwable $e) {
            self::$logger->throwable($e);
        }
        self::$logger->debug(self::$group_name . " - " . $name . " - Finish! (" . round(Utils::microtimeFloat() - $start, 5) . "s)");
    }
}
