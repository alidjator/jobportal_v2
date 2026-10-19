<?php

namespace Src\Workforce\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Clock;
use Src\Workforce\Domain\AmendmentStatus;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionAmendment;
use Src\Workforce\Domain\RequisitionRepository;
use Src\Workforce\Domain\RequisitionStatus;
use Src\Workforce\Domain\RequisitionType;
use stdClass;

final readonly class DatabaseRequisitionRepository implements RequisitionRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const string DAY_FORMAT = 'Y-m-d';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function find(int $id, bool $lock = false): ?Requisition
    {
        $query = $this->connection->table('workforce_requisitions')->where('id', $id);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toRequisition($row);
    }

    public function create(Requisition $requisition): Requisition
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $id = (int) $this->connection->table('workforce_requisitions')->insertGetId([
            ...$this->columns($requisition), 'company_id' => $requisition->companyId, 'legal_entity_id' => $requisition->legalEntityId, 'requester_id' => $requisition->requesterId,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return $this->find($id) ?? throw new \LogicException('The requisition was just stored.');
    }

    public function save(Requisition $requisition): void
    {
        $this->connection->table('workforce_requisitions')->where('id', $requisition->id)->update([...$this->columns($requisition), 'updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    public function search(?int $companyId, ?array $legalEntityIds, ?RequisitionStatus $status, ?RequisitionType $type, ?int $positionId): array
    {
        if ($legalEntityIds === []) {
            return [];
        }

        $query = $this->connection->table('workforce_requisitions');

        foreach ([['company_id', $companyId], ['status', $status?->value], ['type', $type?->value], ['position_id', $positionId]] as [$column, $value]) {
            if ($value !== null) {
                $query->where($column, $value);
            }
        }

        if ($legalEntityIds !== null) {
            $query->whereIn('legal_entity_id', $legalEntityIds);
        }

        return array_map($this->toRequisition(...), $query->orderByDesc('id')->get()->all());
    }

    public function usedHires(int $legalEntityId, int $positionId, int $fiscalYear, ?int $exceptRequisitionId = null): int
    {
        $statuses = array_map(static fn (RequisitionStatus $status): string => $status->value, array_filter(RequisitionStatus::cases(), static fn (RequisitionStatus $status): bool => $status->usesQuota()));

        $query = $this->connection->table('workforce_requisitions')->where('legal_entity_id', $legalEntityId)->where('position_id', $positionId)
            ->where('fiscal_year', $fiscalYear)->where('type', RequisitionType::Planned->value);

        if ($exceptRequisitionId !== null) {
            $query->where('id', '!=', $exceptRequisitionId);
        }

        $used = (int) (clone $query)->whereIn('status', $statuses)->sum('hires');

        // An amendment that waits for a decision holds the extra hires it asks for.
        $pending = $this->connection->table('workforce_requisition_amendments as amendment')
            ->join('workforce_requisitions as requisition', 'requisition.id', '=', 'amendment.requisition_id')
            ->where('amendment.status', AmendmentStatus::PendingApproval->value)->whereNotNull('amendment.hires')
            ->whereIn('requisition.id', (clone $query)->select('id'))
            ->selectRaw('COALESCE(SUM(GREATEST(amendment.hires - requisition.hires, 0)), 0) as extra')->value('extra');

        return $used + (int) $pending;
    }

    public function usedHiresByPosition(int $legalEntityId, int $fiscalYear): array
    {
        $used = [];

        foreach ($this->connection->table('workforce_requisitions')->where('legal_entity_id', $legalEntityId)->where('fiscal_year', $fiscalYear)->where('type', RequisitionType::Planned->value)->distinct()->pluck('position_id') as $positionId) {
            $used[(int) $positionId] = $this->usedHires($legalEntityId, (int) $positionId, $fiscalYear);
        }

        return $used;
    }

    public function findAmendment(int $id, bool $lock = false): ?RequisitionAmendment
    {
        $query = $this->connection->table('workforce_requisition_amendments')->where('id', $id);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toAmendment($row);
    }

    public function createAmendment(RequisitionAmendment $amendment): RequisitionAmendment
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $id = (int) $this->connection->table('workforce_requisition_amendments')->insertGetId([
            'requisition_id' => $amendment->requisitionId, 'hires' => $amendment->hires, 'salary_min' => $amendment->salaryMin, 'salary_max' => $amendment->salaryMax,
            'reason' => $amendment->reason, 'status' => $amendment->status->value, 'requested_by_id' => $amendment->requestedById, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $this->findAmendment($id) ?? throw new \LogicException('The amendment was just stored.');
    }

    public function saveAmendment(RequisitionAmendment $amendment): void
    {
        $this->connection->table('workforce_requisition_amendments')->where('id', $amendment->id)->update([
            'status' => $amendment->status->value, 'approval_id' => $amendment->approvalId, 'decision_reason' => $amendment->decisionReason,
            'decided_at' => $amendment->decidedAt?->format(self::TIME_FORMAT), 'updated_at' => $this->clock->now()->format(self::TIME_FORMAT),
        ]);
    }

    public function amendmentsOf(int $requisitionId): array
    {
        return array_map($this->toAmendment(...), $this->connection->table('workforce_requisition_amendments')->where('requisition_id', $requisitionId)->orderBy('id')->get()->all());
    }

    public function pendingAmendment(int $requisitionId): ?RequisitionAmendment
    {
        $row = $this->connection->table('workforce_requisition_amendments')->where('requisition_id', $requisitionId)->where('status', AmendmentStatus::PendingApproval->value)->first();

        return $row === null ? null : $this->toAmendment($row);
    }

    /** @return array<string, mixed> */
    private function columns(Requisition $requisition): array
    {
        return [
            'type' => $requisition->type->value,
            'status' => $requisition->status->value,
            'position_id' => $requisition->positionId,
            'unit_id' => $requisition->unitId,
            'requested_grade_id' => $requisition->requestedGradeId,
            'hires' => $requisition->hires,
            'job_type_id' => $requisition->jobTypeId,
            'contract_months' => $requisition->contractMonths,
            'target_join_date' => $requisition->targetJoinDate->format(self::DAY_FORMAT),
            'fiscal_year' => $requisition->fiscalYear,
            'cost_centre_id' => $requisition->costCentreId,
            'salary_min' => $requisition->salaryMin,
            'salary_max' => $requisition->salaryMax,
            'justification' => $requisition->justification,
            'replacement_name' => $requisition->replacementName,
            'replacement_leaving_date' => $requisition->replacementLeavingDate?->format(self::DAY_FORMAT),
            'hiring_manager_user_id' => $requisition->hiringManagerUserId,
            'location_id' => $requisition->locationId,
            'approval_id' => $requisition->approvalId,
            'decision_reason' => $requisition->decisionReason,
            'closed_reason' => $requisition->closedReason,
            'submitted_at' => $requisition->submittedAt?->format(self::TIME_FORMAT),
            'decided_at' => $requisition->decidedAt?->format(self::TIME_FORMAT),
            'closed_at' => $requisition->closedAt?->format(self::TIME_FORMAT),
        ];
    }

    private function toRequisition(stdClass $row): Requisition
    {
        return new Requisition(
            (int) $row->id, (int) $row->company_id, (int) $row->legal_entity_id, RequisitionType::from($row->type), RequisitionStatus::from($row->status), (int) $row->position_id,
            (int) $row->unit_id, $this->nullableInt($row->requested_grade_id), (int) $row->hires, (int) $row->job_type_id, $this->nullableInt($row->contract_months),
            new DateTimeImmutable($row->target_join_date), (int) $row->fiscal_year, $this->nullableInt($row->cost_centre_id), $this->nullableInt($row->salary_min),
            $this->nullableInt($row->salary_max), $row->justification, $row->replacement_name, $this->nullableTime($row->replacement_leaving_date),
            (int) $row->hiring_manager_user_id, (int) $row->requester_id, $this->nullableInt($row->approval_id), $row->decision_reason, $row->closed_reason,
            $this->nullableTime($row->submitted_at), $this->nullableTime($row->decided_at), $this->nullableTime($row->closed_at), new DateTimeImmutable($row->created_at),
            $this->nullableInt($row->location_id),
        );
    }

    private function toAmendment(stdClass $row): RequisitionAmendment
    {
        return new RequisitionAmendment(
            (int) $row->id, (int) $row->requisition_id, $this->nullableInt($row->hires), $this->nullableInt($row->salary_min), $this->nullableInt($row->salary_max), $row->reason,
            AmendmentStatus::from($row->status), (int) $row->requested_by_id, $this->nullableInt($row->approval_id), $row->decision_reason, $this->nullableTime($row->decided_at),
            new DateTimeImmutable($row->created_at),
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function nullableTime(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value);
    }
}
