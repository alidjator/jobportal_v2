<?php

namespace Src\Workforce\Application;

use Src\Shared\Application\Approval\ApprovalFact;
use Src\Shared\Application\Approval\ApprovalSubjectType;
use Src\Workforce\Domain\Plan;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionType;

/** The subject types the Workforce context sends through approval, and the facts the conditions of a flow step may read about them. */
final class WorkforceApprovalSubjects
{
    public const string PLAN = 'workforce.plan';

    public const string REQUISITION = 'workforce.requisition';

    public const string REQUISITION_AMENDMENT = 'workforce.requisition_amendment';

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
            new ApprovalSubjectType(self::REQUISITION, 'A requisition to hire for a position.', self::requisitionFactTypes(), ['hiring_manager']),
            new ApprovalSubjectType(self::REQUISITION_AMENDMENT, 'An increase of the hires or the salary budget of an open requisition.', self::requisitionFactTypes(), ['hiring_manager']),
        ];
    }

    /** @return list<ApprovalFact> */
    private static function requisitionFactTypes(): array
    {
        return [
            new ApprovalFact('type', ApprovalFact::STRING, 'Type of the requisition', array_map(static fn (RequisitionType $type): string => $type->value, RequisitionType::cases())),
            new ApprovalFact('grade_rank', ApprovalFact::INTEGER, 'Rank of the requested grade (0 when none is named)'),
            new ApprovalFact('hires', ApprovalFact::INTEGER, 'Number of hires'),
            new ApprovalFact('max_budget', ApprovalFact::INTEGER, 'Highest salary of the budget, in rupiah (0 when none is named)'),
        ];
    }

    /**
     * The facts of a requisition, or of the requisition with an amendment's new values applied (`$hires` and `$salaryMax` replace the current ones).
     *
     * @return array<string, int|string>
     */
    public static function requisitionFacts(Requisition $requisition, int $gradeRank, ?int $hires = null, ?int $salaryMax = null): array
    {
        return ['type' => $requisition->type->value, 'grade_rank' => $gradeRank, 'hires' => $hires ?? $requisition->hires, 'max_budget' => $salaryMax ?? $requisition->salaryMax ?? 0];
    }

    /** @return array<string, int|bool> one value for every fact of the plan subject type */
    public static function planFacts(Plan $plan): array
    {
        return ['additions' => $plan->totalAdditions(), 'estimated_cost' => $plan->totalCost(), 'lines' => count($plan->lines), 'revision' => $plan->version > 1];
    }
}
