<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewKit;
use Src\Recruitment\Domain\InterviewKitRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Finds a kit of the member's own company: a kit of another company is a plain 404. */
final readonly class InterviewKitLookup
{
    public function __construct(private InterviewKitRepository $kits) {}

    /** @throws EntityNotFound */
    public function find(RecruitmentMember $member, int $kitId): InterviewKit
    {
        $kit = $this->kits->find($kitId);

        if (! $kit instanceof InterviewKit || $kit->companyId !== $member->companyId) {
            throw EntityNotFound::of('Interview kit', $kitId);
        }

        return $kit;
    }
}
