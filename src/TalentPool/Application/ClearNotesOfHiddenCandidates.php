<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntryRepository;

/**
 * Deletes the note of every entry whose candidate can no longer be found (profile switched off or incomplete, account closed). The entry stays, so a company can
 * still remove it, but the company's words about the person do not outlive their choice to be found. A candidate who becomes findable again starts with no note.
 * Runs nightly in batches; running it again does nothing.
 */
final readonly class ClearNotesOfHiddenCandidates
{
    private const int BATCH = 500;

    public function __construct(private EntryRepository $entries, private CandidateDirectory $candidates) {}

    /** @return int how many notes were cleared */
    public function execute(): int
    {
        $cleared = 0;
        $after = 0;

        while (($batch = $this->entries->withNotesAfter($after, self::BATCH)) !== []) {
            $findable = array_flip($this->candidates->findableIds(array_values(array_unique(array_map(static fn (Entry $entry): int => $entry->candidateUserId, $batch)))));
            $hidden = [];

            foreach ($batch as $entry) {
                if (! isset($findable[$entry->candidateUserId])) {
                    $hidden[] = (int) $entry->id;
                }
            }

            $cleared += $this->entries->clearNotes($hidden);
            $after = (int) $batch[array_key_last($batch)]->id;
        }

        return $cleared;
    }
}
