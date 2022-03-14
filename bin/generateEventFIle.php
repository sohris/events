<?php

use Sohris\Core\Server;
use Sohris\Event\Event;

include "vendor/autoload.php";

$server = new Server;
$server->loadingServer();
$event = new Event;
$event->install();
$event->saveEventFile();