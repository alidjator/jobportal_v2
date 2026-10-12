<?php

namespace Src\Assessment\Domain;

enum VideoTakeStatus: string
{
    case Recording = 'recording';
    case Complete = 'complete';
    case Rejected = 'rejected';
    case Purged = 'purged';
}
