<?php

namespace Src\Workforce\Domain;

/** `planned` uses the quota of the plan in force, `replacement` replaces someone who leaves and uses none, `unplanned` is outside the plan. */
enum RequisitionType: string
{
    case Planned = 'planned';
    case Replacement = 'replacement';
    case Unplanned = 'unplanned';
}
