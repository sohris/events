<?php

namespace Sohris\Event\Commands;

use React\EventLoop\Loop;
use Sohris\Core\Logger;
use Sohris\Core\Server;
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
    private static Logger $logger;

    protected function configure(): void
    {
        $this
            ->setName("sohris:execute_event")
            ->setDescription('Execute a event registred')
            ->setHelp('This command execute an event that extends the Sohris\Event\Event\EventControl')
            ->addArgument('event', InputArgument::REQUIRED, 'Event Class')
            ->addArgument('log_file_name', InputArgument::OPTIONAL, 'The custom log file, by default the log file is the class name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }
        Server::setOutput($output);
        $event = $input->getArgument("event");
        if (!class_exists($event)) {
            $output->writeln("Class $event not found!");
            return Command::INVALID;
        }

        if (get_parent_class($event) != 'Sohris\Event\Event\EventControl') {
            $output->writeln("The event $event is not register!");
            return Command::INVALID;
        }

        $log_name = strtoupper(str_replace("/", "_", $event));
        if($input->hasArgument("log_file_name"))
        {
            $log_name = $input->getArgument("log_file_name");
        }

        self::$logger = new Logger($log_name);
        self::$event = new $event;

        $info = self::$event->getInfo();
        self::$logger->info("Configuring $info[name]");
        self::$logger->debug("Timer $info[interval_type] - $info[interval_frequency]");
        self::$event->on("start_event", fn () => self::$logger->debug($event . " - Start!"));
        self::$event->on("error", fn ($e) => self::$logger->error("Code: $e[errcode] - Message: $e[errmsg] - File: $e[errfile]($e[errline])"));
        self::$event->on("finish_event", fn () => self::$logger->debug($event . " - Finish!"));        
        self::$event->start();
        Loop::run();
        return Command::SUCCESS;
    }
}
