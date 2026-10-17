<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Src\Recruitment\Application\ApplicantExportRow;
use Src\Recruitment\Application\OtherApplicationRow;
use Src\Recruitment\Application\RecruiterApplicationDetailData;
use Src\Recruitment\Application\RecruiterApplicationQuery;
use Src\Recruitment\Application\RecruiterApplicationReader;
use Src\Recruitment\Application\RecruiterApplicationRow;
use Src\Recruitment\Application\RecruiterHistoryEntry;
use Src\Recruitment\Application\StageClock;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Infrastructure\Persistence\TextSearch;
use stdClass;

/**
 * The applicant's name is searched inside the profile snapshot (JSON), which holds a name only until the account is anonymised. A job has few
 * applications, so the search needs no index. The search ignores case and accents (`TextSearch`).
 */
final readonly class DatabaseRecruiterApplicationReader implements RecruiterApplicationReader
{
    private const int EXPORT_CHUNK = 200;

    private const string NOT_SCHEDULED = 'not_scheduled';

    private const array ROW_COLUMNS = ['a.id', 'a.stage_id', 'a.stage_type', 'a.meets_must_have', 'a.profile_snapshot', 'a.applied_at', 'a.stage_entered_at', 's.name as stage_name', 'r.rating_average', 'r.rating_count'];

    public function __construct(private ConnectionInterface $connection, private DatabaseApplicationAnswers $answers, private Clock $clock) {}

    public function page(RecruiterApplicationQuery $query, PageRequest $request): Page
    {
        $filtered = $this->filtered($query);

        $total = (clone $filtered)->count();
        $direction = $query->newestFirst ? 'desc' : 'asc';
        $now = $this->clock->now();

        $rows = $this->ordered($this->withRatings($filtered), $query, $direction)
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get([...self::ROW_COLUMNS, $this->interviewStateColumn()])
            ->map(fn (stdClass $row): RecruiterApplicationRow => $this->toRow($row, $query->staleAfterDays, $now))
            ->all();

        return new Page($rows, $total, $request);
    }

    public function count(RecruiterApplicationQuery $query): int
    {
        return $this->filtered($query)->count();
    }

    public function export(RecruiterApplicationQuery $query, int $limit): iterable
    {
        $direction = $query->newestFirst ? 'desc' : 'asc';

        // The ids are read first and the rows in chunks, so the export stays consistent while applications arrive; only the ids (at most `$limit`) are held, the rows live one chunk at a time.
        $ids = $this->ordered($this->filtered($query), $query, $direction)->limit($limit)->pluck('a.id')->map(static fn (mixed $id): int => (int) $id)->all();

        $now = $this->clock->now();

        foreach (array_chunk($ids, self::EXPORT_CHUNK) as $chunk) {
            $rows = $this->withRatings($this->connection->table('recruitment_applications as a')->join('recruitment_stages as s', 's.id', '=', 'a.stage_id')->whereIn('a.id', $chunk))
                ->get([...self::ROW_COLUMNS, 'a.job_id', $this->interviewStateColumn()])
                ->keyBy('id');

            foreach ($chunk as $id) {
                $row = $rows[$id] ?? null;

                if ($row instanceof stdClass) {
                    $snapshot = $this->snapshot($row->profile_snapshot);

                    yield new ApplicantExportRow($this->toRow($row, $query->staleAfterDays, $now), $snapshot['email'] ?? null, $snapshot['phone'] ?? null, $this->answers->forApplication($id, (int) $row->job_id));
                }
            }
        }
    }

    private function filtered(RecruiterApplicationQuery $query): Builder
    {
        $filtered = $this->connection->table('recruitment_applications as a')
            ->join('recruitment_stages as s', 's.id', '=', 'a.stage_id')
            ->where('a.job_id', $query->jobId)
            ->when($query->stageId !== null, fn (Builder $b) => $b->where('a.stage_id', $query->stageId))
            ->when($query->stageType !== null, fn (Builder $b) => $b->where('a.stage_type', $query->stageType?->value))
            ->when($query->hiringManagerVisibleOnly, fn (Builder $b) => $b->whereNotNull('a.hiring_manager_visible_at'))
            ->when($query->meetsMustHave !== null, fn (Builder $b) => $b->where('a.meets_must_have', $query->meetsMustHave === true ? 1 : 0));

        if ($query->staleOnly) {
            // Whole Jakarta days: the day the stage was entered, in Jakarta time, is more than the limit before today.
            $zone = new DateTimeZone(StageClock::ZONE);
            $cutoff = $this->clock->now()->setTimezone($zone)->modify("-{$query->staleAfterDays} days")->format('Y-m-d');
            $filtered->whereIn('a.stage_type', [StageType::Applied->value, StageType::Screening->value])
                ->whereRaw("(a.stage_entered_at AT TIME ZONE 'UTC' AT TIME ZONE ?)::date < ?::date", [StageClock::ZONE, $cutoff]);
        }

        if ($query->interviewState !== null) {
            $this->whereInterviewState($filtered, $query->interviewState);
        }

        if ($query->search !== null && $query->search !== '') {
            TextSearch::contains($filtered, new Expression("a.profile_snapshot->>'name'"), $query->search);
        }

        return $filtered;
    }

    /**
     * The state of the interview of an application in an interview stage: the pending or confirmed interview, else `completed` when one was held, else
     * `not_scheduled` (no interview yet, or all cancelled or declined). Null outside an interview stage.
     */
    private function interviewStateColumn(): Expression
    {
        $latest = $this->connection->table('recruitment_interviews as i')
            ->select('i.status')
            ->whereColumn('i.application_id', 'a.id')
            ->whereRaw("i.status IN ('pending', 'confirmed', 'completed')")
            ->orderByRaw("(i.status = 'completed')")
            ->orderByDesc('i.id')
            ->limit(1);

        return new Expression('('.$latest->toSql().') AS interview_state');
    }

    private function whereInterviewState(Builder $filtered, string $state): void
    {
        $filtered->where('a.stage_type', StageType::Interview->value);
        $held = fn (array $statuses) => fn (Builder $interviews) => $interviews->selectRaw('1')->from('recruitment_interviews as i')->whereColumn('i.application_id', 'a.id')->whereIn('i.status', $statuses);

        match ($state) {
            self::NOT_SCHEDULED => $filtered->whereNotExists($held(['pending', 'confirmed', 'completed'])),
            'pending', 'confirmed' => $filtered->whereExists($held([$state])),
            'completed' => $filtered->whereExists($held(['completed']))->whereNotExists($held(['pending', 'confirmed'])),
            default => $filtered->whereRaw('1 = 0'),
        };
    }

    /** By the applied time, or by the time in stage: more days in the stage is an earlier `stage_entered_at`, so the direction is reversed. */
    private function ordered(Builder $builder, RecruiterApplicationQuery $query, string $direction): Builder
    {
        if ($query->sortKey === RecruiterApplicationQuery::SORT_DAYS_IN_STAGE) {
            $builder->orderByRaw("(a.stage_entered_at AT TIME ZONE 'UTC' AT TIME ZONE '".StageClock::ZONE."')::date ".($direction === 'desc' ? 'asc' : 'desc'));
        } else {
            $builder->orderBy('a.applied_at', $direction);
        }

        return $builder->orderBy('a.id', $direction);
    }

    /** The ratings are summed per listed application in a lateral join, so only the ratings of the rows on the page are read. */
    private function withRatings(Builder $builder): Builder
    {
        $ratings = $this->connection->table('recruitment_application_ratings')
            ->whereColumn('application_id', 'a.id')
            ->selectRaw('AVG(stars) as rating_average, COUNT(*) as rating_count');

        return $builder->leftJoinLateral($ratings, 'r');
    }

    public function countByStage(int $jobId, bool $hiringManagerVisibleOnly = false): array
    {
        $counts = [];

        foreach ($this->connection->table('recruitment_applications')->where('job_id', $jobId)->when($hiringManagerVisibleOnly, fn (Builder $b) => $b->whereNotNull('hiring_manager_visible_at'))->groupBy('stage_id')->selectRaw('stage_id, COUNT(*) as total')->get() as $row) {
            $counts[(int) $row->stage_id] = (int) $row->total;
        }

        return $counts;
    }

    public function find(int $id): ?RecruiterApplicationDetailData
    {
        $row = $this->connection->table('recruitment_applications')->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        return new RecruiterApplicationDetailData(
            (int) $row->id,
            (int) $row->job_id,
            (int) $row->company_id,
            (int) $row->stage_id,
            StageType::from($row->stage_type),
            $row->meets_must_have === null ? null : (bool) $row->meets_must_have,
            $row->cover_letter,
            $row->resume_path === null ? null : (string) $row->resume_name,
            $row->withdrawn_note,
            $this->snapshot($row->profile_snapshot),
            $this->answers->forApplication($id, (int) $row->job_id),
            $this->history($id),
            $this->time((string) $row->applied_at),
            $this->time((string) $row->stage_entered_at),
        );
    }

    public function otherApplications(int $applicationId): array
    {
        $own = $this->connection->table('recruitment_applications')->where('id', $applicationId)->first(['user_id', 'company_id']);

        if ($own === null) {
            return [];
        }

        return $this->connection->table('recruitment_applications as a')
            ->join('recruitment_stages as s', 's.id', '=', 'a.stage_id')
            ->where('a.user_id', $own->user_id)->where('a.company_id', $own->company_id)->where('a.id', '!=', $applicationId)
            ->orderByDesc('a.applied_at')->orderByDesc('a.id')
            ->get(['a.id', 'a.job_id', 'a.stage_type', 's.name as stage_name', 'a.applied_at', 'a.hiring_manager_visible_at'])
            ->map(fn (stdClass $row): OtherApplicationRow => new OtherApplicationRow((int) $row->id, (int) $row->job_id, StageType::from($row->stage_type), (string) $row->stage_name, $this->time((string) $row->applied_at), $row->hiring_manager_visible_at !== null))
            ->all();
    }

    public function activeOtherCounts(array $applicationIds, ?array $visibleJobIds, array $boundJobIds = []): array
    {
        if ($applicationIds === [] || $visibleJobIds === []) {
            return [];
        }

        $counts = [];
        $query = $this->connection->table('recruitment_applications as a')
            ->join('recruitment_applications as o', function ($join): void {
                $join->on('o.user_id', '=', 'a.user_id')->on('o.company_id', '=', 'a.company_id')->on('o.id', '!=', 'a.id');
            })
            ->whereIn('a.id', $applicationIds)->whereNotNull('a.profile_snapshot')
            ->whereNotIn('o.stage_type', [StageType::Rejected->value, StageType::Withdrawn->value]);

        if ($visibleJobIds !== null) {
            $query->whereIn('o.job_id', $visibleJobIds);
        }

        // On a job where the member is a bound hiring manager, only the applications that carry the marker count.
        if ($boundJobIds !== []) {
            $query->where(fn (Builder $b) => $b->whereNotIn('o.job_id', $boundJobIds)->orWhereNotNull('o.hiring_manager_visible_at'));
        }

        foreach ($query->groupBy('a.id')->selectRaw('a.id, COUNT(*) as total')->get() as $row) {
            $counts[(int) $row->id] = (int) $row->total;
        }

        return $counts;
    }

    /** @return list<RecruiterHistoryEntry> */
    private function history(int $applicationId): array
    {
        return $this->connection->table('recruitment_application_stage_history')
            ->where('application_id', $applicationId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $entry): RecruiterHistoryEntry => new RecruiterHistoryEntry(
                $entry->from_stage_id === null ? null : (int) $entry->from_stage_id,
                (int) $entry->to_stage_id,
                $entry->from_type === null ? null : StageType::from($entry->from_type),
                StageType::from($entry->to_type),
                StageMoveActor::from($entry->actor_kind),
                $entry->actor_id === null ? null : (int) $entry->actor_id,
                $entry->rejection_reason === null ? null : RejectionReason::from($entry->rejection_reason),
                $entry->note,
                $this->time((string) $entry->created_at),
            ))
            ->all();
    }

    private function toRow(stdClass $row, int $staleAfterDays, DateTimeImmutable $now): RecruiterApplicationRow
    {
        $type = StageType::from($row->stage_type);
        $entered = $this->time((string) $row->stage_entered_at);
        $days = StageClock::days($entered, $now);

        $snapshot = $this->snapshot($row->profile_snapshot);
        $experience = $snapshot['experiences'][0] ?? null;
        $salary = $snapshot['expected_salary'] ?? null;

        return new RecruiterApplicationRow(
            (int) $row->id,
            $snapshot === null,
            $snapshot['name'] ?? null,
            $snapshot['headline'] ?? null,
            is_array($experience) ? ['title' => (string) $experience['job_title'], 'company' => (string) $experience['company_name']] : null,
            $snapshot['location']['city'] ?? null,
            is_array($salary) ? ['amount' => (int) $salary['amount'], 'period' => $salary['period'] ?? null] : null,
            $snapshot['age'] ?? null,
            $row->meets_must_have === null ? null : (bool) $row->meets_must_have,
            (int) $row->stage_id,
            StageType::from($row->stage_type),
            (string) $row->stage_name,
            $this->time((string) $row->applied_at),
            $row->rating_average === null ? null : round((float) $row->rating_average, 1),
            (int) $row->rating_count,
            null,
            $entered,
            $days,
            StageClock::isStale($type, $days, $staleAfterDays),
            0,
            $type === StageType::Interview ? (string) ($row->interview_state ?? self::NOT_SCHEDULED) : null,
        );
    }

    /** @return array<string, mixed>|null null when there is no snapshot (the account was anonymised) */
    private function snapshot(mixed $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $decoded = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
