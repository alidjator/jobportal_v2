<?php

namespace Src\Recruitment\Domain;

/** `open` while answers are awaited, `complete` when every reviewer answered every active application, `closed` when the requester ended it early. */
enum ReviewRequestStatus: string
{
    case Open = 'open';
    case Complete = 'complete';
    case Closed = 'closed';
}
