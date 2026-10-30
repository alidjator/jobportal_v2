<?php

namespace Src\Platform\Application\Queue;

enum QueueAlertKind: string
{
    case Failed = 'failed';
    case Stalled = 'stalled';
}
