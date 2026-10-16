<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Organization\LegalEntityDirectory;
use Src\Shared\Application\Organization\PositionDirectory;
use Src\Shared\Application\Organization\StructureDirectory;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Workforce\Application\Plan\FiscalCalendar;
use Src\Workforce\Domain\RequisitionType;

/**
 * Checks what is written in a requisition (`docs/design/erp-requisitions.md` section 3) and works out what follows from it: the unit of the position, the fiscal year of the
 * target join date and the default cost centre of the unit. A replacement names who leaves; any other type keeps no replacement details.
 */
final readonly class RequisitionRules
{
    public const int MAX_CONTRACT_MONTHS = 120;

    public function __construct(
        private PositionDirectory $positions,
        private StructureDirectory $structure,
        private LegalEntityDirectory $entities,
        private ReferenceValues $references,
        private CompanyTeam $team,
        private FiscalCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @return array<string, mixed> the requisition properties the input decides, keyed by property name
     *
     * @throws BusinessRuleViolation `invalid-requisition-field`
     */
    public function fields(int $companyId, int $legalEntityId, RequisitionInput $input): array
    {
        $now = $this->clock->now();
        $position = $this->positions->ofEntity($legalEntityId, $now)[$input->positionId] ?? throw $this->invalid('The position is not a position of this legal entity.');

        if (! $position->active) {
            throw $this->invalid("Position {$position->code} is deactivated.");
        }

        $this->assertGrade($input->requestedGradeId, $position->minGradeId, $position->maxGradeId, $this->entities->groupOf($legalEntityId));

        if (! $this->references->isActive('job-types', $input->jobTypeId)) {
            throw $this->invalid('The employment type is not an active job type.');
        }

        $contract = $input->jobTypeId === $this->references->idBySlug('job-types', 'contract');

        if ($contract && ($input->contractMonths === null || $input->contractMonths < 1 || $input->contractMonths > self::MAX_CONTRACT_MONTHS)) {
            throw $this->invalid('A contract names its length in months, 1 to '.self::MAX_CONTRACT_MONTHS.'.');
        }

        if ($input->targetJoinDate->format('Y-m-d') < $now->format('Y-m-d')) {
            throw $this->invalid('The target join date cannot be in the past.');
        }

        if ($input->salaryMin !== null && $input->salaryMax !== null && $input->salaryMin > $input->salaryMax) {
            throw $this->invalid('The minimum salary is above the maximum.');
        }

        $costCentreId = $input->costCentreId ?? $this->structure->unitCostCentre($position->unitId, $now);

        if ($costCentreId !== null && ! in_array($costCentreId, $this->structure->activeCostCentreIds($legalEntityId), true)) {
            throw $this->invalid('The cost centre is not an active cost centre of this legal entity.');
        }

        if (! array_key_exists($input->hiringManagerUserId, $this->team->roles($companyId))) {
            throw $this->invalid('The hiring manager is not a member of this tenant.');
        }

        $replacement = $input->type === RequisitionType::Replacement;

        if ($replacement && ($input->replacementName === null || trim($input->replacementName) === '' || $input->replacementLeavingDate === null)) {
            throw $this->invalid('A replacement names who leaves and when.');
        }

        return [
            'type' => $input->type,
            'positionId' => $input->positionId,
            'unitId' => $position->unitId,
            'requestedGradeId' => $input->requestedGradeId,
            'hires' => $input->hires,
            'jobTypeId' => $input->jobTypeId,
            'contractMonths' => $contract ? $input->contractMonths : null,
            'targetJoinDate' => $input->targetJoinDate,
            'fiscalYear' => $this->calendar->yearOf($input->targetJoinDate, $companyId),
            'costCentreId' => $costCentreId,
            'salaryMin' => $input->salaryMin,
            'salaryMax' => $input->salaryMax,
            'justification' => trim($input->justification),
            'replacementName' => $replacement ? trim((string) $input->replacementName) : null,
            'replacementLeavingDate' => $replacement ? $input->replacementLeavingDate : null,
            'hiringManagerUserId' => $input->hiringManagerUserId,
        ];
    }

    /** The rank of the grade, for the facts of the approval; 0 when the requisition names no grade. */
    public function rankOf(?int $gradeId): int
    {
        return $gradeId === null ? 0 : ($this->structure->grade($gradeId)->rank ?? 0);
    }

    private function assertGrade(?int $gradeId, ?int $minGradeId, ?int $maxGradeId, ?int $groupId): void
    {
        if ($gradeId === null) {
            return;
        }

        $grade = $this->structure->grade($gradeId);

        if ($grade === null || $grade->groupId !== $groupId || ! $grade->active) {
            throw $this->invalid('The requested grade is not an active grade of this group.');
        }

        $min = $minGradeId === null ? null : $this->structure->grade($minGradeId)?->rank;
        $max = $maxGradeId === null ? null : $this->structure->grade($maxGradeId)?->rank;

        if (($min !== null && $grade->rank < $min) || ($max !== null && $grade->rank > $max)) {
            throw $this->invalid('The requested grade is outside the grade range of the position.');
        }
    }

    private function invalid(string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-requisition-field');
    }
}
