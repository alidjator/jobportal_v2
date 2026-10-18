<?php

namespace Src\Platform\Infrastructure\Scheduler;

use Illuminate\Console\Scheduling\Event;

/**
 * The stable name of a scheduled task: the artisan command it runs (`app:purge-things`; Laravel fills `description` with the command's description, which is text that may be
 * reworded), or the `->name()` of a closure task. It does not change when a task moves between files or its time changes. A test checks that every task has a distinct one.
 */
final class ScheduledTaskName
{
    public static function of(Event $task): string
    {
        // The command line is `'php' 'artisan' name --option=...`, with the arguments quoted by Laravel.
        if (preg_match("/artisan'?\s+'?([a-z0-9:_-]+)/i", (string) $task->command, $match) === 1) {
            return $match[1];
        }

        // A closure task: Laravel only fills `description` for commands, so here it is the name given with `->name()`.
        if ($task->description !== null && $task->description !== '') {
            return $task->description;
        }

        return 'unnamed-'.md5((string) $task->command.$task->expression);
    }
}
