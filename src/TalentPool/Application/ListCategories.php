<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\TalentPool\Domain\Category;
use Src\TalentPool\Domain\CategoryRepository;
use Src\TalentPool\Domain\EntryRepository;

/** The categories of the member's company by name, each with how many candidates it holds. */
final readonly class ListCategories
{
    public function __construct(private TalentPoolAccess $access, private CategoryRepository $categories, private EntryRepository $entries) {}

    /**
     * @return list<CategoryView>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function execute(Actor $actor): array
    {
        $member = $this->access->member($actor);
        $counts = $this->entries->countsByCategory($member->companyId);

        return array_map(
            static fn (Category $category): CategoryView => new CategoryView((int) $category->id, $category->name, $counts[(int) $category->id] ?? 0),
            $this->categories->ofCompany($member->companyId),
        );
    }
}
