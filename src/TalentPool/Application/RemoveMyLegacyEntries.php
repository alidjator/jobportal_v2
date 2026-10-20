<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\TalentPool\Domain\EntryRepository;

/**
 * A job seeker removes themselves from every private list that a company carried over from karir.pkp (`legacy` source). Entries a company saved later (from the search or from an
 * application) are not touched: those have their own basis and the consent revocation of an application.
 */
final readonly class RemoveMyLegacyEntries
{
    public function __construct(private EntryRepository $entries) {}

    /**
     * @return int how many entries were deleted
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor): int
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can remove themselves from a company list.', 'job-seeker-only');
        }

        return $this->entries->deleteLegacyOfCandidate($actor->id);
    }
}
