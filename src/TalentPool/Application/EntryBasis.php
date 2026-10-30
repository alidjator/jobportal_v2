<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Recruitment\PoolApplications;
use Src\TalentPool\Domain\ConsentWindow;
use Src\TalentPool\Domain\Entry;

/**
 * The basis that keeps an entry available (`docs/design/phase-8-talent-pool.md` section 1): the person can be found, or has an application to the company with a consent
 * submitted within the last 24 months. Entries of several companies may be asked at once; one read per port for the whole batch.
 */
final readonly class EntryBasis
{
    public function __construct(private CandidateDirectory $candidates, private PoolApplications $applications, private Clock $clock) {}

    /**
     * The entries that have no basis any more. A person who blocked the company stays a basis, because a block hides and never ends a right (the cleanups keep the entry);
     * with `$honouringBlocks` such an entry counts as without basis, which is how the invitation flow sees that it may not be used (an entry that rests on the
     * person's consent from an application stays usable).
     *
     * @param  list<Entry>  $entries
     * @return list<Entry>
     */
    public function withoutBasis(array $entries, bool $honouringBlocks = false): array
    {
        if ($entries === []) {
            return [];
        }

        $findable = [];

        if ($honouringBlocks) {
            $byCompany = [];

            foreach ($entries as $entry) {
                $byCompany[$entry->companyId][] = $entry->candidateUserId;
            }

            foreach ($byCompany as $companyId => $userIds) {
                foreach ($this->candidates->findableIds(array_values(array_unique($userIds)), $companyId) as $userId) {
                    $findable[$companyId][$userId] = true;
                }
            }
        } else {
            foreach ($this->candidates->findableIds(array_values(array_unique(array_map(static fn (Entry $entry): int => $entry->candidateUserId, $entries)))) as $userId) {
                $findable[0][$userId] = true;
            }
        }

        $isFindable = static fn (Entry $entry): bool => isset($findable[$honouringBlocks ? $entry->companyId : 0][$entry->candidateUserId]);
        $since = ConsentWindow::since($this->clock->now());
        $notFindable = [];

        foreach ($entries as $entry) {
            if (! $isFindable($entry)) {
                $notFindable[$entry->companyId][] = $entry->candidateUserId;
            }
        }

        $consenting = [];

        foreach ($notFindable as $companyId => $userIds) {
            $consenting[$companyId] = array_flip($this->applications->consentingUserIds($companyId, array_values(array_unique($userIds)), $since));
        }

        return array_values(array_filter($entries, static fn (Entry $entry): bool => ! $isFindable($entry) && ! isset($consenting[$entry->companyId][$entry->candidateUserId])));
    }
}
