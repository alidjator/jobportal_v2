<?php

namespace Src\Approval\Domain;

/** How a condition compares a fact with its value: at least, at most, equal, or one of a list. */
enum ConditionOperator: string
{
    case Gte = 'gte';
    case Lte = 'lte';
    case Eq = 'eq';
    case In = 'in';
}
