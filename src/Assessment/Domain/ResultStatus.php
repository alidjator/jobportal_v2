<?php

namespace Src\Assessment\Domain;

enum ResultStatus: string
{
    /** Every answer that can be scored has its score. */
    case Scored = 'scored';

    /** A long-text answer still waits for its assessor. */
    case PendingScoring = 'pending_scoring';
}
