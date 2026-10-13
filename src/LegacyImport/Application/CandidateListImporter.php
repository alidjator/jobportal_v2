<?php

namespace Src\LegacyImport\Application;

use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * The shared part of the importers of what hangs on a job seeker profile (experience, education, skills, languages): it reads a legacy table that points at a
 * `candidates` row, finds the account and the profile the candidate became (a row of a candidate whose profile was not imported is skipped), counts the rows
 * the user already has (for the position), with no limit (the import keeps the legacy data as it is), and writes each mapped row through the writer. A subclass only says how one row is mapped.
 */
abstract class CandidateListImporter implements Importer
{
    private const int BATCH = 200;

    /** @var array<int, int> how many rows each user has, counted while the run goes on */
    private array $counts = [];

    public function __construct(
        protected readonly LegacySource $legacy,
        protected readonly JobSeekerWriter $writer,
        protected readonly TransactionManager $transactions,
    ) {}

    /** The legacy table, and the column of it that holds the legacy candidate id. */
    abstract protected function table(): string;

    abstract protected function candidateColumn(): string;

    /** The id-map entity (and the name in the report) of the rows this importer writes. */
    abstract protected function itemEntity(): string;

    /** The kind of row the writer writes. */
    abstract protected function kind(): string;

    /**
     * Anything a whole batch of rows needs to be mapped (for example master maps), looked up once.
     *
     * @param  list<stdClass>  $rows
     * @return array<string, mixed>
     */
    protected function lookups(ImportContext $context, array $rows): array
    {
        return [];
    }

    /**
     * Maps one legacy row to the columns of the new row, or gives the reason to skip it.
     *
     * @param  array<string, mixed>  $lookups
     * @return array<string, mixed>|string the columns (without `user_id`, which is added), or a skip reason code
     */
    abstract protected function map(stdClass $row, int $userId, ImportContext $context, array $lookups): array|string;

    /** How many rows of this kind the user has so far (those already there plus the ones this run added). */
    protected function countOf(int $userId): int
    {
        return $this->counts[$userId] ?? 0;
    }

    /** A legacy timestamp (read as UTC, like every timestamp of the new schema), or the run's time when it is empty. */
    protected function time(mixed $value, ImportContext $context): string
    {
        $time = $value === null || $value === '' ? $context->clock->now() : new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));

        return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Called when a run starts, to forget what the previous run counted. */
    protected function reset(): void {}

    public function dependsOn(): array
    {
        return [ProfilesImporter::ENTITY];
    }

    public function run(ImportContext $context): void
    {
        $this->counts = [];
        $this->reset();

        $this->legacy->chunk($this->table(), self::BATCH, function (array $rows) use ($context): void {
            $column = $this->candidateColumn();
            $candidateIds = array_values(array_unique(array_map(static fn (stdClass $row): int => (int) $row->{$column}, $rows)));
            $ids = array_map(static fn (stdClass $row): int => (int) $row->id, $rows);

            $owners = [];

            foreach ($this->legacy->whereIn('candidates', 'id', $candidateIds, ['id', 'user_id']) as $candidate) {
                $owners[(int) $candidate->id] = (int) $candidate->user_id;
            }

            $state = [
                'already' => $context->idMap->findMany($this->itemEntity(), $ids),
                'profiles' => $context->idMap->findMany(ProfilesImporter::PROFILE, $candidateIds),
                'users' => $context->idMap->findMany(AccountsImporter::USER, array_values($owners)),
                'owners' => $owners,
                'lookups' => $this->lookups($context, $rows),
            ];

            $this->transactions->run(function () use ($rows, $context, $state, $column): void {
                foreach ($rows as $row) {
                    $this->importRow($row, (int) $row->{$column}, $context, $state);
                }
            });
        });
    }

    /** @param  array<string, mixed>  $state */
    private function importRow(stdClass $row, int $candidateId, ImportContext $context, array $state): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $entity = $this->itemEntity();
        $report->read($entity);

        if (isset($state['already'][$legacyId])) {
            $report->note($entity, 'already-imported');

            return;
        }

        $userId = isset($state['profiles'][$candidateId], $state['owners'][$candidateId]) ? ($state['users'][$state['owners'][$candidateId]] ?? null) : null;

        if ($userId === null) {
            $report->skipped($entity, $legacyId, 'profile-not-imported');

            return;
        }

        $this->counts[$userId] ??= $this->writer->count($this->kind(), $userId);

        $mapped = $this->map($row, $userId, $context, $state['lookups']);

        if (is_string($mapped)) {
            $report->skipped($entity, $legacyId, $mapped);

            return;
        }

        $this->counts[$userId]++;

        if ($context->write) {
            $newId = $this->writer->insert($this->kind(), ['user_id' => $userId, ...$mapped]);
            $context->idMap->remember($entity, $legacyId, $newId, $context->clock->now());
        }

        $report->imported($entity);
    }
}
