<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\TalentPool\Domain\CategoryRepository;
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntryRepository;

/** A member moves a saved candidate to one of the company's categories (or out of any), and/or changes or clears the note. */
final readonly class ChangeEntry
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
     * @throws BusinessRuleViolation `note-too-long`
     */
    public function execute(Actor $actor, int $entryId, EntryChanges $changes): EntryView
    {
        $member = $this->access->member($actor);
        $note = $changes->changesNote ? Entry::cleanNote($changes->note) : null;

        $entry = $this->transactions->run(function () use ($member, $entryId, $changes, $note): Entry {
            $entry = $this->entries->find($member->companyId, $entryId) ?? throw EntityNotFound::of('Talent pool entry', $entryId);

            if ($changes->changesCategory) {
                if ($changes->categoryId !== null) {
                    $this->categories->find($member->companyId, $changes->categoryId) ?? throw EntityNotFound::of('Category', $changes->categoryId);
                }

                $this->entries->changeCategory($member->companyId, $entryId, $changes->categoryId);
            }

            if ($changes->changesNote) {
                $this->entries->changeNote($member->companyId, $entryId, $note);
            }

            return new Entry(
                $entry->companyId,
                $entry->candidateUserId,
                $changes->changesCategory ? $changes->categoryId : $entry->categoryId,
                $entry->createdAt,
                $entry->id,
                $changes->changesNote ? $note : $entry->note,
            );
        });

        return $this->views->views($member->companyId, [$entry])[0];
    }
}
