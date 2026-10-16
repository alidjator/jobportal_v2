<?php

namespace Src\Approval\Domain;

/** How many approvers of a step must approve it: any one of them, or all of them. */
enum StepMode: string
{
    case Any = 'any';
    case All = 'all';
}
