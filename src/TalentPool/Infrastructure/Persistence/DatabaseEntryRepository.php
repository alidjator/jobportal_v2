<?php

namespace Src\TalentPool\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntryRepository;
use Src\TalentPool\Domain\EntrySource;
use stdClass;

final readonly class DatabaseEntryRepository implements EntryRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(Entry $entry): Entry
    {
        try {
            $id = (int) $this->connection->table('talentpool_entries')->insertGetId([
                'company_id' => $entry->companyId,
                'candidate_user_id' => $entry->candidateUserId,
                'category_id' => $entry->categoryId,
                'note' => $entry->note,
                'source' => $entry->source->value,
                'application_id' => $entry->applicationId,
                'saved_by_user_id' => $entry->savedByUserId,
                'created_at' => $this->format($entry->createdAt),
                'updated_at' => $this->format($entry->createdAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('This candidate is already in your talent pool.', 'already-in-talent-pool');
        }

        return new Entry($entry->companyId, $entry->candidateUserId, $entry->categoryId, $entry->createdAt, $id, $entry->note, $entry->source, $entry->applicationId, $entry->savedByUserId);
    }

    public function find(int $companyId, int $id): ?Entry
    {
        $row = $this->connection->table('talentpool_entries')->where('company_id', $companyId)->where('id', $id)->first();

        return $row === null ? null : $this->toEntry($row);
    }

    public function exists(int $companyId, int $candidateUserId): bool
    {
        return $this->connection->table('talentpool_entries')->where('company_id', $companyId)->where('candidate_user_id', $candidateUserId)->exists();
    }

    public function count(int $companyId): int
    {
        return $this->connection->table('talentpool_entries')->where('company_id', $companyId)->count();
    }

    public function changeCategory(int $companyId, int $id, ?int $categoryId): void
    {
        $this->connection->table('talentpool_entries')->where('company_id', $companyId)->where('id', $id)->update([
            'category_id' => $categoryId,
            'updated_at' => $this->format(new DateTimeImmutable),
        ]);
    }

    public function changeNote(int $companyId, int $id, ?string $note): void
    {
        $this->connection->table('talentpool_entries')->where('company_id', $companyId)->where('id', $id)->update([
            'note' => $note,
            'updated_at' => $this->format(new DateTimeImmutable),
        ]);
    }

    public function remove(int $companyId, int $id): void
    {
        $this->connection->table('talentpool_entries')->where('company_id', $companyId)->where('id', $id)->delete();
    }

    public function allOfCompany(int $companyId, ?int $categoryId = null, ?EntrySource $source = null): array
    {
        return array_map(
            fn (stdClass $row): Entry => $this->toEntry($row),
            $this->scoped($companyId, $categoryId, $source)->orderByDesc('created_at')->orderByDesc('id')->get()->all(),
        );
    }

    public function pageOfCompany(int $companyId, ?int $categoryId, ?EntrySource $source, PageRequest $request): Page
    {
        $query = $this->scoped($companyId, $categoryId, $source);

        $total = (clone $query)->count();
        $rows = $total === 0 ? [] : $query->orderByDesc('created_at')->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get()->all();

        return new Page(array_map(fn (stdClass $row): Entry => $this->toEntry($row), $rows), $total, $request);
    }

    public function findMany(int $companyId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map(
            fn (stdClass $row): Entry => $this->toEntry($row),
            $this->connection->table('talentpool_entries')->where('company_id', $companyId)->whereIn('id', $ids)->get()->all(),
        );
    }

    public function findOfCandidate(int $companyId, int $candidateUserId): ?Entry
    {
        $row = $this->connection->table('talentpool_entries')->where('company_id', $companyId)->where('candidate_user_id', $candidateUserId)->first();

        return $row === null ? null : $this->toEntry($row);
    }

    public function countsByCategory(int $companyId): array
    {
        $counts = [];

        foreach ($this->connection->table('talentpool_entries')->where('company_id', $companyId)->whereNotNull('category_id')->groupBy('category_id')
            ->get(['category_id', $this->connection->raw('count(*) as total')]) as $row) {
            $counts[(int) $row->category_id] = (int) $row->total;
        }

        return $counts;
    }

    public function ofCandidate(int $candidateUserId): array
    {
        return array_map(
            fn (stdClass $row): Entry => $this->toEntry($row),
            $this->connection->table('talentpool_entries')->where('candidate_user_id', $candidateUserId)->orderBy('created_at')->orderBy('id')->get()->all(),
        );
    }

    public function deleteOfCandidate(int $candidateUserId): void
    {
        $this->connection->table('talentpool_entries')->where('candidate_user_id', $candidateUserId)->delete();
    }

    public function withNotesAfter(int $afterId, int $limit): array
    {
        return array_map(
            fn (stdClass $row): Entry => $this->toEntry($row),
            $this->connection->table('talentpool_entries')->whereNotNull('note')->where('id', '>', $afterId)->orderBy('id')->limit($limit)->get()->all(),
        );
    }

    public function clearNotes(array $ids): int
    {
        return $ids === [] ? 0 : $this->connection->table('talentpool_entries')->whereIn('id', $ids)->whereNotNull('note')->update(['note' => null, 'updated_at' => $this->format(new DateTimeImmutable)]);
    }

    public function restingOnConsentAfter(int $afterId, int $limit): array
    {
        return array_map(
            fn (stdClass $row): Entry => $this->toEntry($row),
            $this->connection->table('talentpool_entries')->where('source', EntrySource::Application->value)->whereNotNull('application_id')->where('id', '>', $afterId)->orderBy('id')->limit($limit)->get()->all(),
        );
    }

    public function restingOnLegacyAfter(int $afterId, int $limit, DateTimeImmutable $untouchedSince): array
    {
        return array_map(
            fn (stdClass $row): Entry => $this->toEntry($row),
            $this->connection->table('talentpool_entries')->where('source', EntrySource::Legacy->value)->where('updated_at', '<=', $this->format($untouchedSince))->where('id', '>', $afterId)->orderBy('id')->limit($limit)->get()->all(),
        );
    }

    public function deleteLegacyOfCandidate(int $candidateUserId): int
    {
        return $this->connection->table('talentpool_entries')->where('candidate_user_id', $candidateUserId)->where('source', EntrySource::Legacy->value)->delete();
    }

    public function removeMany(array $ids): int
    {
        return $ids === [] ? 0 : $this->connection->table('talentpool_entries')->whereIn('id', $ids)->delete();
    }

    private function scoped(int $companyId, ?int $categoryId, ?EntrySource $source): Builder
    {
        $query = $this->connection->table('talentpool_entries')->where('company_id', $companyId);

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($source !== null) {
            $query->where('source', $source->value);
        }

        return $query;
    }

    private function toEntry(stdClass $row): Entry
    {
        return new Entry(
            (int) $row->company_id,
            (int) $row->candidate_user_id,
            $row->category_id === null ? null : (int) $row->category_id,
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
            (int) $row->id,
            $row->note === null ? null : (string) $row->note,
            EntrySource::from((string) $row->source),
            $row->application_id === null ? null : (int) $row->application_id,
            $row->saved_by_user_id === null ? null : (int) $row->saved_by_user_id,
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
