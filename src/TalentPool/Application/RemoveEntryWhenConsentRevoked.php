<?php

namespace Src\TalentPool\Application;

use Src\TalentPool\Domain\EntryRepository;

/**
 * An applicant took back a consent: the company's entry that rests on a consenting application goes, unless another consenting application of the company within 24
 * months or the candidate search still keeps the person available. Its note goes with it. An entry found in the search, or saved from an application of a findable person, stays; running it again does nothing.
 */
final readonly class RemoveEntryWhenConsentRevoked
{
    public function __construct(private EntryRepository $entries, private EntryBasis $basis) {}

    public function execute(int $companyId, int $applicantUserId): void
    {
        $entry = $this->entries->findOfCandidate($companyId, $applicantUserId);

        if ($entry === null || $entry->applicationId === null || $this->basis->withoutBasis([$entry]) === []) {
            return;
        }

        $this->entries->removeMany([(int) $entry->id]);
    }
}
