<?php

namespace Src\Workforce\Application;

use Src\Shared\Application\Approval\ApprovalFact;
use Src\Shared\Application\Approval\ApprovalSubjectType;
use Src\Workforce\Domain\Plan;

/** The subject types the Workforce context sends through approval, and the facts the conditions of a flow step may read about them. */
final class WorkforceApprovalSubjects
{
    public const string PLAN = 'workforce.plan';

    /** @return list<ApprovalSubjectType> */
    public static function types(): array
    {
        return [
            new ApprovalSubjectType(
                self::PLAN, 'A manpower plan of a legal entity for a fiscal year.',
                [
                    new ApprovalFact('additions', ApprovalFact::INTEGER, 'Total additions of the plan'),
                    new ApprovalFact('estimated_cost', ApprovalFact::INTEGER, 'Total estimated annual cost of the plan, in rupiah'),
                    new ApprovalFact('lines', ApprovalFact::INTEGER, 'Number of positions in the plan'),
                    new ApprovalFact('revision', ApprovalFact::BOOLEAN, 'Whether the plan revises a version already in force'),
                ],
            ),
        ];
    }

    /** @return array<string, int|bool> one value for every fact of the plan subject type */
    public static function planFacts(Plan $plan): array
    {
        return ['additions' => $plan->totalAdditions(), 'estimated_cost' => $plan->totalCost(), 'lines' => count($plan->lines), 'revision' => $plan->version > 1];
    }
}
