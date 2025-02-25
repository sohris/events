# Sohris Events

The `sohris/events` library is a PHP package designed to facilitate the creation and management of asynchronous and parallel events. By leveraging annotations, developers can efficiently schedule and control task execution within their applications.

## Installation

To install the `sohris/events` package, use Composer:

```bash
composer require sohris/events
```

Ensure that your project meets the following requirements:

-   PHP 7.4 or higher
-   `ext-parallel` extension
-   `doctrine/annotations` version ^1.13
-   `mtdowling/cron-expression` version ^1.2
-   `sohris/core` version ^0.5


## Usage

After installation, you can create events using annotations to define their scheduling and behavior.



## Available Annotations

The `sohris/events` library provides annotations to configure events. The primary annotations include:

-   `@StartRunning`: Indicates that the event should automatically execute when the server starts.
-   `@Time`: Sets the event's execution frequency. It can be of type `Interval` (interval in seconds) or `Cron` (using crontab syntax).

**Usage Example:**

```php
/**
 * @Time(
 *     type="Cron",
 *     time="00 00 * * *"  // Executes daily at midnight
 * )
 * @StartRunning
 */
```

## Implementation Example

Below is an example of an event class utilizing the provided annotations:

```php
<?php

namespace App\Events\Template;

use Sohris\Event\Annotations\Time;
use Sohris\Event\Annotations\StartRunning;
use Sohris\Event\Event\EventControl;

/**
 * Test Class
 *
 * Represents an event that executes automatically
 * at a specific time, defined by cron syntax.
 *
 * Annotations:
 * @Time(
 *     type="Cron",
 *     time="00 00 * * *"  // Executes daily at midnight
 * )
 * @StartRunning  // Indicates the event starts automatically upon system load
 */
class Test extends EventControl {
    /**
     * Run Method
     *
     * The main method executed when the event is triggered.
     * Should contain the desired processing logic.
     */
    public static function run() {
        // Implement the job logic here
    }

    /**
     * FirstRun Method
     *
     * Called during the event's first execution.
     * Can be used for initializations or specific configurations.
     */
    public static function firstRun() {
        // Implement initial setup logic, if necessary
    }
}```
