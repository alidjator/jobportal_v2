<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\TalentPool\Domain\CategoryRepository;
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntryRepository;

/** A member moves a saved candidate to one of the company's categories, or out of any category (`null`). */
final readonly class ChangeEntryCategory
{
    public function __construct(
        private TalentPoolAccess $access,
        private CategoryRepository $categories,
        private EntryRepository $entries,
        private EntryViews $views,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound the entry or the category (missing or another company's)
     */
    public function execute(Actor $actor, int $entryId, ?int $categoryId): EntryView
    {
        $member = $this->access->member($actor);

        $entry = $this->transactions->run(function () use ($member, $entryId, $categoryId): Entry {
            $entry = $this->entries->find($member->companyId, $entryId) ?? throw EntityNotFound::of('Talent pool entry', $entryId);

            if ($categoryId !== null) {
                $this->categories->find($member->companyId, $categoryId) ?? throw EntityNotFound::of('Category', $categoryId);
            }

            $this->entries->changeCategory($member->companyId, $entryId, $categoryId);

            return new Entry($entry->companyId, $entry->candidateUserId, $categoryId, $entry->createdAt, $entry->id);
        });

        return $this->views->views($member->companyId, [$entry])[0];
    }
}
