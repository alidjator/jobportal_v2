<?php

namespace Src\Workforce\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Clock;
use Src\Workforce\Domain\Plan;
use Src\Workforce\Domain\PlanLine;
use Src\Workforce\Domain\PlanRepository;
use Src\Workforce\Domain\PlanStatus;
use stdClass;

final readonly class DatabasePlanRepository implements PlanRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function find(int $id, bool $lock = false): ?Plan
    {
        $query = $this->connection->table('workforce_plans')->where('id', $id);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->load([$row])[0];
    }

    public function create(Plan $plan): Plan
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $id = (int) $this->connection->table('workforce_plans')->insertGetId([
            'legal_entity_id' => $plan->legalEntityId, 'fiscal_year' => $plan->fiscalYear, 'version' => $plan->version, 'status' => $plan->status->value,
            'created_by_id' => $plan->createdById, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->replaceLines($id, $plan->lines);

        return $this->find($id) ?? throw new \LogicException('The plan was just stored.');
    }

    public function save(Plan $plan): void
    {
        $this->connection->table('workforce_plans')->where('id', $plan->id)->update([
            'status' => $plan->status->value,
            'approval_id' => $plan->approvalId,
            'submitted_at' => $plan->submittedAt?->format(self::TIME_FORMAT),
            'decided_at' => $plan->decidedAt?->format(self::TIME_FORMAT),
            'decision_reason' => $plan->decisionReason,
            'updated_at' => $this->clock->now()->format(self::TIME_FORMAT),
        ]);
    }

    public function replaceLines(int $planId, array $lines): void
    {
        $this->connection->table('workforce_plan_lines')->where('plan_id', $planId)->delete();

        /** @var PlanLine $line */
        foreach ($lines as $line) {
            $lineId = (int) $this->connection->table('workforce_plan_lines')->insertGetId([
                'plan_id' => $planId, 'position_id' => $line->positionId, 'opening_headcount' => $line->openingHeadcount,
                'target_headcount' => $line->targetHeadcount, 'estimated_annual_cost' => $line->estimatedAnnualCost,
            ]);

            $this->connection->table('workforce_plan_line_months')->insert(array_map(
                static fn (int $additions, int $index): array => ['line_id' => $lineId, 'month' => $index + 1, 'additions' => $additions], $line->months, array_keys($line->months),
            ));
        }

        $this->connection->table('workforce_plans')->where('id', $planId)->update(['updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    public function ofEntities(array $legalEntityIds, ?int $fiscalYear, ?PlanStatus $status): array
    {
        if ($legalEntityIds === []) {
            return [];
        }

        $query = $this->connection->table('workforce_plans')->whereIn('legal_entity_id', $legalEntityIds);

        if ($fiscalYear !== null) {
            $query->where('fiscal_year', $fiscalYear);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $this->load($query->orderByDesc('fiscal_year')->orderBy('legal_entity_id')->orderByDesc('version')->get()->all());
    }

    public function versionsOf(int $legalEntityId, int $fiscalYear): array
    {
        return $this->load($this->connection->table('workforce_plans')->where('legal_entity_id', $legalEntityId)->where('fiscal_year', $fiscalYear)->orderBy('version')->get()->all());
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<Plan>
     */
    private function load(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $planIds = array_map(static fn (stdClass $row): int => (int) $row->id, $rows);
        $lineRows = $this->connection->table('workforce_plan_lines')->whereIn('plan_id', $planIds)->orderBy('id')->get();
        $months = [];

        foreach ($this->connection->table('workforce_plan_line_months')->whereIn('line_id', $lineRows->pluck('id')->all())->orderBy('month')->get() as $month) {
            $months[(int) $month->line_id][] = (int) $month->additions;
        }

        $lines = [];

        foreach ($lineRows as $line) {
            $lines[(int) $line->plan_id][] = new PlanLine(
                (int) $line->position_id, (int) $line->opening_headcount, (int) $line->target_headcount, $months[(int) $line->id] ?? array_fill(0, PlanLine::MONTHS, 0),
                $line->estimated_annual_cost === null ? null : (int) $line->estimated_annual_cost,
            );
        }

        return array_map(static fn (stdClass $row): Plan => new Plan(
            (int) $row->id, (int) $row->legal_entity_id, (int) $row->fiscal_year, (int) $row->version, PlanStatus::from($row->status), (int) $row->created_by_id,
            $lines[(int) $row->id] ?? [], $row->approval_id === null ? null : (int) $row->approval_id,
            $row->submitted_at === null ? null : new DateTimeImmutable($row->submitted_at), $row->decided_at === null ? null : new DateTimeImmutable($row->decided_at),
            $row->decision_reason, new DateTimeImmutable($row->created_at),
        ), $rows);
    }
}
