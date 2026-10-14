<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\TalentPool\Domain\Category;
use Src\TalentPool\Domain\CategoryRepository;
use Src\TalentPool\Domain\EntryRepository;

/** A company admin renames a category of their company. */
final readonly class RenameCategory
{
    public function __construct(private TalentPoolAccess $access, private CategoryRepository $categories, private EntryRepository $entries, private TransactionManager $transactions) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound the category is missing or another company's
     * @throws BusinessRuleViolation `category-name-invalid`
     * @throws StateConflict `category-name-taken`
     */
    public function execute(Actor $actor, int $id, string $name): CategoryView
    {
        $member = $this->access->member($actor);
        $name = Category::cleanName($name);

        return $this->transactions->run(function () use ($member, $id, $name): CategoryView {
            $this->categories->find($member->companyId, $id) ?? throw EntityNotFound::of('Category', $id);

            if ($this->categories->nameTaken($member->companyId, $name, $id)) {
                throw new StateConflict('Your company already has a category with this name.', 'category-name-taken');
            }

            $this->categories->rename($member->companyId, $id, $name);

            return new CategoryView($id, $name, $this->entries->countsByCategory($member->companyId)[$id] ?? 0);
        });
    }
}
