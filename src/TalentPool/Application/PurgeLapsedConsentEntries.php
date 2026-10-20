<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Clock;
use Src\TalentPool\Domain\ConsentWindow;
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntryRepository;

/**
 * Deletes the entries that were saved from a consenting application and whose only basis was that consent: the person cannot be found and their last consenting application is
 * older than 24 months (`docs/design/phase-8-talent-pool.md` section 1). The same rule ends a private list carried over from karir.pkp (`legacy` source) 24 months after the import
 * (or the company's last change to the entry, `docs/design/phase-8-activation.md` section 9). An entry of a findable candidate stays whatever its source. Runs nightly in batches next to
 * `ClearNotesOfHiddenCandidates`; running it again does nothing.
 */
final readonly class PurgeLapsedConsentEntries
{
    private const int BATCH = 500;

    public function __construct(private EntryRepository $entries, private EntryBasis $basis, private Clock $clock) {}

    /** @return int how many entries were deleted */
    public function execute(): int
    {
        $deleted = 0;
        $after = 0;

        while (($batch = $this->entries->restingOnConsentAfter($after, self::BATCH)) !== []) {
            $deleted += $this->entries->removeMany(array_map(static fn (Entry $entry): int => (int) $entry->id, $this->basis->withoutBasis($batch)));
            $after = (int) $batch[array_key_last($batch)]->id;
        }

        $untouchedSince = ConsentWindow::since($this->clock->now());
        $after = 0;

        while (($batch = $this->entries->restingOnLegacyAfter($after, self::BATCH, $untouchedSince)) !== []) {
            $deleted += $this->entries->removeMany(array_map(static fn (Entry $entry): int => (int) $entry->id, $this->basis->withoutBasis($batch)));
            $after = (int) $batch[array_key_last($batch)]->id;
        }

        return $deleted;
    }
}
