<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Src\Recruitment\Application\ApplicantExportRow;
use Src\Recruitment\Application\RecruiterApplicationDetailData;
use Src\Recruitment\Application\RecruiterApplicationQuery;
use Src\Recruitment\Application\RecruiterApplicationReader;
use Src\Recruitment\Application\RecruiterApplicationRow;
use Src\Recruitment\Application\RecruiterHistoryEntry;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;
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

    private const array ROW_COLUMNS = ['a.id', 'a.stage_id', 'a.stage_type', 'a.meets_must_have', 'a.profile_snapshot', 'a.applied_at', 's.name as stage_name', 'r.rating_average', 'r.rating_count'];

    public function __construct(private ConnectionInterface $connection, private DatabaseApplicationAnswers $answers) {}

    public function page(RecruiterApplicationQuery $query, PageRequest $request): Page
    {
        $filtered = $this->filtered($query);

        $total = (clone $filtered)->count();
        $direction = $query->newestFirst ? 'desc' : 'asc';

        $rows = $this->withRatings($filtered)
            ->orderBy('a.applied_at', $direction)
            ->orderBy('a.id', $direction)
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get(self::ROW_COLUMNS)
            ->map(fn (stdClass $row): RecruiterApplicationRow => $this->toRow($row))
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
        $ids = $this->filtered($query)->orderBy('a.applied_at', $direction)->orderBy('a.id', $direction)->limit($limit)->pluck('a.id')->map(static fn (mixed $id): int => (int) $id)->all();

        foreach (array_chunk($ids, self::EXPORT_CHUNK) as $chunk) {
            $rows = $this->withRatings($this->connection->table('recruitment_applications as a')->join('recruitment_stages as s', 's.id', '=', 'a.stage_id')->whereIn('a.id', $chunk))
                ->get([...self::ROW_COLUMNS, 'a.job_id'])
                ->keyBy('id');

            foreach ($chunk as $id) {
                $row = $rows[$id] ?? null;

                if ($row instanceof stdClass) {
                    $snapshot = $this->snapshot($row->profile_snapshot);

                    yield new ApplicantExportRow($this->toRow($row), $snapshot['email'] ?? null, $snapshot['phone'] ?? null, $this->answers->forApplication($id, (int) $row->job_id));
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
            ->when($query->meetsMustHave !== null, fn (Builder $b) => $b->where('a.meets_must_have', $query->meetsMustHave === true ? 1 : 0));

        if ($query->search !== null && $query->search !== '') {
            TextSearch::contains($filtered, new Expression("a.profile_snapshot->>'name'"), $query->search);
        }

        return $filtered;
    }

    /** The ratings are summed per listed application in a lateral join, so only the ratings of the rows on the page are read. */
    private function withRatings(Builder $builder): Builder
    {
        $ratings = $this->connection->table('recruitment_application_ratings')
            ->whereColumn('application_id', 'a.id')
            ->selectRaw('AVG(stars) as rating_average, COUNT(*) as rating_count');

        return $builder->leftJoinLateral($ratings, 'r');
    }

    public function countByStage(int $jobId): array
    {
        $counts = [];

        foreach ($this->connection->table('recruitment_applications')->where('job_id', $jobId)->groupBy('stage_id')->selectRaw('stage_id, COUNT(*) as total')->get() as $row) {
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
        );
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

    private function toRow(stdClass $row): RecruiterApplicationRow
    {
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
