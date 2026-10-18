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
     * The entries that have no basis any more.
     *
     * @param  list<Entry>  $entries
     * @return list<Entry>
     */
    public function withoutBasis(array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        $findable = array_flip($this->candidates->findableIds(array_values(array_unique(array_map(static fn (Entry $entry): int => $entry->candidateUserId, $entries)))));
        $since = ConsentWindow::since($this->clock->now());
        $notFindable = [];

        foreach ($entries as $entry) {
            if (! isset($findable[$entry->candidateUserId])) {
                $notFindable[$entry->companyId][] = $entry->candidateUserId;
            }
        }

        $consenting = [];

        foreach ($notFindable as $companyId => $userIds) {
            $consenting[$companyId] = array_flip($this->applications->consentingUserIds($companyId, array_values(array_unique($userIds)), $since));
        }

        return array_values(array_filter($entries, static fn (Entry $entry): bool => ! isset($findable[$entry->candidateUserId]) && ! isset($consenting[$entry->companyId][$entry->candidateUserId])));
    }
}
