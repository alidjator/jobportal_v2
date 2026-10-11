<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Accounts\ErasureStep;
use Src\TalentPool\Domain\EntryRepository;

/** Deletes every company's entry for a candidate whose account was anonymised. Running it again does nothing. */
final readonly class EraseTalentEntries implements ErasureStep
{
    public function __construct(private EntryRepository $entries) {}

    public function name(): string
    {
        return 'talent-entries';
    }

    public function execute(int $userId): void
    {
        $this->entries->deleteOfCandidate($userId);
    }
}
