<?php

namespace Src\LegacyImport\Application;

/**
 * What a run did, per entity: how many legacy rows it read, imported and skipped, and which rows it skipped with the reason code. Rows are named by
 * their **legacy id and a reason code only** (never a name, an email or any personal content), so the report can be kept and shared. A "note" is a
 * count of something worth knowing that is not a skip (for example a value that was emptied).
 */
final class ImportReport
{
    /** @var array<string, array{read: int, imported: int, skipped: int, skipped_rows: list<array{legacy_id: int, reason: string}>, notes: array<string, int>, unmatched: list<array<string, mixed>>}> */
    private array $entities = [];

    public function read(string $entity, int $count = 1): void
    {
        $this->entry($entity);
        $this->entities[$entity]['read'] += $count;
    }

    public function imported(string $entity, int $count = 1): void
    {
        $this->entry($entity);
        $this->entities[$entity]['imported'] += $count;
    }

    public function skipped(string $entity, int $legacyId, string $reason): void
    {
        $this->entry($entity);
        $this->entities[$entity]['skipped']++;
        $this->entities[$entity]['skipped_rows'][] = ['legacy_id' => $legacyId, 'reason' => $reason];
    }

    public function note(string $entity, string $code, int $count = 1): void
    {
        $this->entry($entity);
        $this->entities[$entity]['notes'][$code] = ($this->entities[$entity]['notes'][$code] ?? 0) + $count;
    }

    /**
     * A legacy value that has no counterpart (a master item, a language, a pipeline group). These are configuration, not personal data, so they are
     * listed with their names.
     *
     * @param  array<string, mixed>  $details
     */
    public function unmatched(string $entity, array $details): void
    {
        $this->entry($entity);
        $this->entities[$entity]['unmatched'][] = $details;
    }

    /** @return array<string, mixed> */
    public function toArray(bool $write): array
    {
        ksort($this->entities);

        return ['mode' => $write ? 'write' : 'dry-run', 'entities' => $this->entities];
    }

    /** @return list<string> a short text summary, one line per entity */
    public function summary(): array
    {
        $lines = [];

        foreach ($this->toArray(false)['entities'] as $entity => $data) {
            $line = sprintf('%s: read %d, imported %d, skipped %d', $entity, $data['read'], $data['imported'], $data['skipped']);

            if ($data['unmatched'] !== []) {
                $line .= sprintf(', unmatched %d', count($data['unmatched']));
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function entry(string $entity): void
    {
        $this->entities[$entity] ??= ['read' => 0, 'imported' => 0, 'skipped' => 0, 'skipped_rows' => [], 'notes' => [], 'unmatched' => []];
    }
}
