<?php

namespace Src\TalentPool\Application;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\TalentPool\Domain\CategoryRepository;
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntryRepository;

/** The rules every new entry follows, whether the member found the candidate in the search or saved an applicant: the category is the company's own, a person is saved once per company, a company keeps at most 1,000. Call it inside a transaction. */
final readonly class NewEntries
{
    public const int MAX_ENTRIES = 1000;

    public function __construct(private CategoryRepository $categories, private EntryRepository $entries) {}

    /**
     * @throws EntityNotFound the category (missing or another company's)
     * @throws BusinessRuleViolation `talent-pool-limit`
     * @throws StateConflict `already-in-talent-pool`
     */
    public function add(Entry $entry): Entry
    {
        if ($entry->categoryId !== null) {
            $this->categories->find($entry->companyId, $entry->categoryId) ?? throw EntityNotFound::of('Category', $entry->categoryId);
        }

        if ($this->entries->exists($entry->companyId, $entry->candidateUserId)) {
            throw new StateConflict('This candidate is already in your talent pool.', 'already-in-talent-pool');
        }

        if ($this->entries->count($entry->companyId) >= self::MAX_ENTRIES) {
            throw new BusinessRuleViolation('A company keeps at most :max candidates in its talent pool.', 'talent-pool-limit', parameters: ['max' => self::MAX_ENTRIES]);
        }

        return $this->entries->add($entry);
    }
}
