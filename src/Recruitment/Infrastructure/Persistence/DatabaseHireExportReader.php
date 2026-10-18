<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Recruitment\Application\HireExportFilter;
use Src\Recruitment\Application\HireExportOffer;
use Src\Recruitment\Application\HireExportReader;
use Src\Recruitment\Application\HireExportRow;
use Src\Recruitment\Domain\StageType;
use stdClass;

/** The moment of a hire is the last stage move into `hired` from another stage type (a move between two hired stages is not a hire), read from the stage history. */
final readonly class DatabaseHireExportReader implements HireExportReader
{
    private const int CHUNK = 200;

    public function __construct(private ConnectionInterface $connection) {}

    public function count(HireExportFilter $filter): int
    {
        return $this->selection($filter)->count();
    }

    public function export(HireExportFilter $filter, int $limit): iterable
    {
        // The ids are read first and the rows in chunks, so the export stays consistent while hires arrive; only the ids (at most `$limit`) are held.
        $ids = $this->selection($filter)->orderBy('h.hired_at')->orderBy('a.id')->limit($limit)->pluck('a.id')->map(static fn (mixed $id): int => (int) $id)->all();

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $rows = $this->selection($filter)->whereIn('a.id', $chunk)->get(['a.id', 'a.job_id', 'a.join_date', 'a.profile_snapshot', 'h.hired_at'])->keyBy('id');
            $offers = $this->offers($chunk);

            foreach ($chunk as $id) {
                $row = $rows[$id] ?? null;

                if ($row instanceof stdClass) {
                    $snapshot = $row->profile_snapshot === null ? null : json_decode((string) $row->profile_snapshot, true, 512, JSON_THROW_ON_ERROR);
                    $snapshot = is_array($snapshot) ? $snapshot : null;

                    yield new HireExportRow(
                        $id,
                        (int) $row->job_id,
                        $this->time((string) $row->hired_at),
                        $row->join_date === null ? null : new DateTimeImmutable((string) $row->join_date, new DateTimeZone('UTC')),
                        $snapshot === null,
                        $snapshot['name'] ?? null,
                        $snapshot['email'] ?? null,
                        $snapshot['phone'] ?? null,
                        $snapshot['location']['city'] ?? null,
                        $offers[$id] ?? null,
                    );
                }
            }
        }
    }

    private function selection(HireExportFilter $filter): Builder
    {
        $hiredAt = $this->connection->table('recruitment_application_stage_history')
            ->where('to_type', StageType::Hired->value)
            ->where(fn (Builder $b) => $b->whereNull('from_type')->orWhere('from_type', '!=', StageType::Hired->value))
            ->groupBy('application_id')
            ->select(['application_id', $this->connection->raw('MAX(created_at) as hired_at')]);

        return $this->connection->table('recruitment_applications as a')
            ->joinSub($hiredAt, 'h', 'h.application_id', '=', 'a.id')
            ->where('a.company_id', $filter->companyId)
            ->where('a.stage_type', StageType::Hired->value)
            ->where('h.hired_at', '>=', $filter->from->format('Y-m-d H:i:s'))
            ->where('h.hired_at', '<', $filter->until->format('Y-m-d H:i:s'))
            ->when($filter->jobIds !== null, fn (Builder $b) => $b->whereIn('a.job_id', $filter->jobIds))
            ->when($filter->boundJobIds !== [], fn (Builder $b) => $b->where(fn (Builder $inner) => $inner->whereNotIn('a.job_id', $filter->boundJobIds)->orWhereNotNull('a.hiring_manager_visible_at')));
    }

    /**
     * The latest accepted offer of each application.
     *
     * @param  list<int>  $applicationIds
     * @return array<int, HireExportOffer>
     */
    private function offers(array $applicationIds): array
    {
        $offers = [];

        foreach ($this->connection->table('recruitment_offers')
            ->whereIn('application_id', $applicationIds)
            ->where('status', 'accepted')
            ->whereNotNull('answered_at')
            ->orderByDesc('answered_at')->orderByDesc('id')
            ->get(['application_id', 'position', 'salary', 'start_date', 'answered_at']) as $offer) {
            $offers[(int) $offer->application_id] ??= new HireExportOffer(
                (string) $offer->position,
                (int) $offer->salary,
                new DateTimeImmutable((string) $offer->start_date, new DateTimeZone('UTC')),
                $this->time((string) $offer->answered_at),
            );
        }

        return $offers;
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
