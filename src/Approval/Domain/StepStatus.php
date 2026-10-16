<?php

namespace Src\Approval\Domain;

/** A step of a running approval: waiting for the steps before it, being decided now, or approved. */
enum StepStatus: string
{
    case Waiting = 'waiting';
    case Pending = 'pending';
    case Approved = 'approved';
}
