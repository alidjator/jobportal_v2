<?php

namespace Src\Shared\Infrastructure\Queue;

/** The three named queues, processed in this order, so mass mail never delays a verification code. */
enum QueueName: string
{
    case Critical = 'critical';
    case Default = 'default';
    case Bulk = 'bulk';
}
