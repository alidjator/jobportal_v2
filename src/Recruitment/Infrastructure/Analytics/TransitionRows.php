<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\MatchBand;
use Src\Recruitment\Domain\StageMoveActor;

/**
 * The stage moves of a time range read from the history, each with the seconds the application spent in the stage it left (since its previous history entry) and
 * since it was submitted. The initial entry of an application (nothing to applied) is not a move. Shared by the daily summary and the live medians, so both agree.
 */
final readonly class TransitionRows
{
    private const int CHUNK = 1000;

    public function __construct(private ConnectionInterface $connection) {}

    /**
     * @param  string  $startUtc  `Y-m-d H:i:s`, included
     * @param  string  $endUtc  `Y-m-d H:i:s`, not included
     * @return \Generator<int, array{company_id: int, job_id: int, actor_id: int, from_type: string, to_type: string, reason: string, source: string, band: string, created_at: string, in_stage: int, since_applied: int}>
     */
    public function between(string $startUtc, string $endUtc, ?int $companyId): \Generator
    {
        $lastId = 0;

        while (true) {
            $rows = $this->connection->table('recruitment_application_stage_history as h')
                ->join('recruitment_applications as a', 'a.id', '=', 'h.application_id')
                ->whereNotNull('h.from_type')
                ->where('h.created_at', '>=', $startUtc)
                ->where('h.created_at', '<', $endUtc)
                ->where('h.id', '>', $lastId)
                ->when($companyId !== null, fn ($query) => $query->where('a.company_id', $companyId))
                ->orderBy('h.id')
                ->limit(self::CHUNK)
                ->get([
                    'h.id', 'h.application_id', 'h.actor_kind', 'h.actor_id', 'h.from_type', 'h.to_type', 'h.rejection_reason', 'h.created_at',
                    'a.company_id', 'a.job_id', 'a.source', 'a.match_score', 'a.applied_at',
                ]);

            if ($rows->isEmpty()) {
                return;
            }

            $previous = $this->previousTimes($rows->pluck('application_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(), (int) $rows->last()->id);

            foreach ($rows as $row) {
                $id = (int) $row->id;
                $enteredAt = $this->previousOf($previous[(int) $row->application_id] ?? [], $id) ?? (string) $row->applied_at;
                $at = $this->seconds((string) $row->created_at);

                yield [
                    'company_id' => (int) $row->company_id,
                    'job_id' => (int) $row->job_id,
                    'actor_id' => $row->actor_kind === StageMoveActor::Recruiter->value ? (int) $row->actor_id : 0,
                    'from_type' => (string) $row->from_type,
                    'to_type' => (string) $row->to_type,
                    'reason' => $row->rejection_reason === null ? 'none' : (string) $row->rejection_reason,
                    'source' => $row->source === null ? 'unknown' : (string) $row->source,
                    'band' => MatchBand::of($row->match_score === null ? null : (int) $row->match_score)->value,
                    'created_at' => (string) $row->created_at,
                    'in_stage' => max(0, $at - $this->seconds($enteredAt)),
                    'since_applied' => max(0, $at - $this->seconds((string) $row->applied_at)),
                ];

                $lastId = $id;
            }
        }
    }

    /**
     * The id and time of every history entry up to an id, for these applications, in id order.
     *
     * @param  list<int>  $applicationIds
     * @return array<int, array<int, string>> application id => (history id => time)
     */
    private function previousTimes(array $applicationIds, int $maxId): array
    {
        $times = [];

        foreach ($this->connection->table('recruitment_application_stage_history')
            ->whereIn('application_id', $applicationIds)
            ->where('id', '<=', $maxId)
            ->orderBy('id')
            ->get(['id', 'application_id', 'created_at']) as $row) {
            $times[(int) $row->application_id][(int) $row->id] = (string) $row->created_at;
        }

        return $times;
    }

    /** @param  array<int, string>  $entries  history id => time, in id order */
    private function previousOf(array $entries, int $id): ?string
    {
        $found = null;

        foreach ($entries as $entryId => $time) {
            if ($entryId >= $id) {
                break;
            }

            $found = $time;
        }

        return $found;
    }

    private function seconds(string $utc): int
    {
        return (int) strtotime($utc.' UTC');
    }
}
