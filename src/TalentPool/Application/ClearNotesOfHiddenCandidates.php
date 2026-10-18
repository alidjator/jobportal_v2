<?php

namespace Src\TalentPool\Application;

use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntryRepository;

/**
 * Deletes the note of every entry that has no basis any more: the candidate can no longer be found (profile switched off or incomplete, account closed) and no
 * consenting application within 24 months keeps them available (`EntryBasis`). The entry stays, so a company can still remove it, but the company's words about the
 * person do not outlive their choice. A candidate who becomes available again starts with no note. Runs nightly in batches; running it again does nothing.
 */
final readonly class ClearNotesOfHiddenCandidates
{
    private const int BATCH = 500;

    public function __construct(private EntryRepository $entries, private EntryBasis $basis) {}

    /** @return int how many notes were cleared */
    public function execute(): int
    {
        $cleared = 0;
        $after = 0;

        while (($batch = $this->entries->withNotesAfter($after, self::BATCH)) !== []) {
            $cleared += $this->entries->clearNotes(array_map(static fn (Entry $entry): int => (int) $entry->id, $this->basis->withoutBasis($batch)));
            $after = (int) $batch[array_key_last($batch)]->id;
        }

        return $cleared;
    }
}
