<?php

namespace Sohris\Event\Commands;

use React\EventLoop\Loop;
use Sohris\Core\Utils;
use Sohris\Event\Event\EventControl;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;


class ExecuteEvent extends Command
{
    /**
     * @var EventControl
     */
    private static $event;
    private static $memory_usage = [
        "max" => 0,
        "min" => 0,
        "avg" => 0,
        "cur" => 0,
        "historic" => []
    ];


    protected function configure(): void
    {
        $this
            ->setName("sohris:execute_event")
            ->setDescription('Execute a event registred')
            ->setHelp('This command execute an event that extends the Sohris\Event\Event\EventControl')
            ->addArgument('event', InputArgument::OPTIONAL, 'Event Class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }
        $event = $input->getArgument("event");
        if (!class_exists($event)) {
            $output->writeln("Class $event not found!");
            return Command::INVALID;
        }
        if (get_parent_class($event) != 'Sohris\Event\Event\EventControl') {
            $output->writeln("The event $event is not register!");
            return Command::INVALID;
        }

        self::$event = new $event;


        $info_section = $output->section();

        $info = self::$event->getInfo();

        $info_section->writeln([
            "===============Info==============",
            "Event: ". $info['name'],
            "Type: " . $info['interval_type'],
            "Frequency: " . $info['interval_frequency']
        ]);

        $memory_usage_section = $output->section();
        $stats_section = $output->section();
        $error_section = $output->section();

        Loop::addPeriodicTimer(5, function () use (&$memory_usage_section) {
            self::$memory_usage['cur'] = memory_get_usage(1);
            self::$memory_usage['max'] = self::$memory_usage['cur'] > self::$memory_usage['max'] ? self::$memory_usage['cur'] : self::$memory_usage['max'];
            self::$memory_usage['min'] = self::$memory_usage['min'] == 0 ? self::$memory_usage['min'] : (self::$memory_usage['cur'] < self::$memory_usage['min'] ? self::$memory_usage['cur'] : self::$memory_usage['min']);
            self::$memory_usage['historic'][] = self::$memory_usage['cur'];
            self::$memory_usage['historic'] = array_slice(self::$memory_usage['historic'], 0, 20);
            self::$memory_usage['avg'] = array_reduce(self::$memory_usage['historic'], fn ($a, $b) => $a + $b, 0) / count(self::$memory_usage['historic']);
            self::writeMemory($memory_usage_section);
        });

        Loop::addPeriodicTimer(5, function () use (&$stats_section) {
            self::writeStats($stats_section);
        });
        Loop::addPeriodicTimer(5, function () use (&$error_section) {
            self::writeError($error_section);
        });

        self::writeMemory($memory_usage_section);
        self::writeStats($stats_section);
        self::writeError($error_section);
        self::$event->start();
        Loop::run();
        return Command::SUCCESS;
    }

    private static function writeMemory(ConsoleSectionOutput &$section)
    {
        $section->overwrite([
            "=============Memory==============",
            "Memory " . Utils::bytesToHuman(self::$memory_usage['cur']) .
                " - Max (" . Utils::bytesToHuman(self::$memory_usage['max']) .
                ") - Min (" . Utils::bytesToHuman(self::$memory_usage['min']) .
                ") -  Avg (" . Utils::bytesToHuman(self::$memory_usage['avg']) . ")"
        ]);
    }

    private static function writeStats(ConsoleSectionOutput &$section)
    {
        $stats = self::$event->getStats();
        $section->overwrite([
            "==============Stats==============",
            "Uptime: " . $stats['uptime'] . "s",
            "Restart: " . $stats['restart'],
            "Memory: " . Utils::bytesToHuman($stats['memory']),
            "Execution Count: " . $stats['total_run'],
            "Execution Time Count: " .round($stats['total_time_exec'],3) . "s",
            "AVG Time: " . round($stats['avg_time'], 3) . "s",
            "Last Execution: " . $stats['last_run']
        ]);
    }

    private static function writeError(ConsoleSectionOutput &$section)
    {
        $stats = self::$event->getStats();
        $error = $stats['last_error'];
        $section->overwrite([
            "==============Error==============",
            'Timestamp: ' . $error['timestamp'],
            'Error: ' . $error['message'],
            'Error Code: ' . $error['code'],
            'File: ' . $error['file'],
            'Line: ' . $error['line'],
            'Trace: ' . json_encode($error['trace'])
        ]);
    }
}
