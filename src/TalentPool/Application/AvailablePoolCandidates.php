<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\TalentPool\PoolCandidates;
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntryRepository;

/** Tells the invitation flow whom a company's pool entries stand for, and which of them may still be invited. */
final readonly class AvailablePoolCandidates implements PoolCandidates
{
    public function __construct(private EntryRepository $entries, private EntryBasis $basis) {}

    public function availableUserIds(int $companyId, array $entryIds): array
    {
        $entries = $this->entries->findMany($companyId, $entryIds);
        $unavailable = array_flip(array_map(static fn (Entry $entry): int => (int) $entry->id, $this->basis->withoutBasis($entries)));
        $users = [];

        foreach ($entries as $entry) {
            $users[(int) $entry->id] = isset($unavailable[(int) $entry->id]) ? null : $entry->candidateUserId;
        }

        return $users;
    }
}
