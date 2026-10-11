<?php

namespace Src\Shared\Infrastructure\Queue;

/**
 * The three named queues, processed in this order, so mass mail never delays a verification code. `bulk` is read through its own connection (`retry_after`
 * belongs to a connection, and its jobs may run for minutes); the other two use the default connection.
 */
enum QueueName: string
{
    case Critical = 'critical';
    case Default = 'default';
    case Bulk = 'bulk';

    /** The connection a job of this queue is sent to; null means the default one, which is also what `bulk` gets when jobs run in the request (`sync`). */
    public function connection(): ?string
    {
        if ($this !== self::Bulk || in_array(config('queue.connections.bulk.driver'), ['sync', 'deferred', 'background', 'null'], true)) {
            return null;
        }

        return 'bulk';
    }
}
