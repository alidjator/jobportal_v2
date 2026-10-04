<?php

namespace Src\Identity\Application\Terms;

use Src\Identity\Domain\TermsAcceptanceRepository;
use Src\Shared\Application\Legal\ActiveLegalVersions;

/** Compares the legal documents in force with what a user has accepted. A document type that was never published is not required. */
final readonly class TermsAcceptanceChecker
{
    public function __construct(
        private ActiveLegalVersions $activeVersions,
        private TermsAcceptanceRepository $acceptances,
    ) {}

    /** @return list<TermsRequirement> one per document in force, ordered by type */
    public function requirements(int $userId): array
    {
        $active = $this->activeVersions->active();
        ksort($active);

        $accepted = $this->acceptances->acceptedTypes($userId, $active);

        $requirements = [];

        foreach ($active as $type => $version) {
            $requirements[] = new TermsRequirement($type, $version, in_array($type, $accepted, true));
        }

        return $requirements;
    }
}
