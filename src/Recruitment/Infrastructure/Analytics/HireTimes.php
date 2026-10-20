<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;

/**
 * When the applications that are hired now were hired. The hire moment of an application is its latest move into a hired stage in the stage history; an application
 * that left the hired stage again (withdrawn after the offer, moved back) is not counted.
 */
final readonly class HireTimes
{
    public function __construct(private ConnectionInterface $connection) {}

    /** @return array<int, list<string>> the hire moments (UTC, `Y-m-d H:i:s`) of each job that has a hire, oldest first; for one company, or for every company when null */
    public function of(?int $companyId): array
    {
        $rows = $this->connection->table('recruitment_applications as a')
            ->joinSub(
                $this->connection->table('recruitment_application_stage_history')->where('to_type', 'hired')
                    ->groupBy('application_id')->select(['application_id', $this->connection->raw('MAX(created_at) as hired_at')]),
                'h',
                'h.application_id', '=', 'a.id',
            )
            ->where('a.stage_type', 'hired')
            ->whereNull('a.migrated_at')
            ->when($companyId !== null, fn ($query) => $query->where('a.company_id', $companyId))
            ->orderBy('a.job_id')->orderBy('h.hired_at')
            ->get(['a.job_id', 'h.hired_at']);

        $hires = [];

        foreach ($rows as $row) {
            $hires[(int) $row->job_id][] = substr((string) $row->hired_at, 0, 19);
        }

        return $hires;
    }
}
