<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\TalentPool\Domain\Category;
use Src\TalentPool\Domain\CategoryRepository;

/** A member adds a category to their company: a name of 1 to 50 characters that no other category of the company has (whatever the case), at most 30 categories. */
final readonly class CreateCategory
{
    public const int MAX_CATEGORIES = 30;

    public function __construct(private TalentPoolAccess $access, private CategoryRepository $categories, private Clock $clock, private TransactionManager $transactions) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws BusinessRuleViolation `category-name-invalid` or `category-limit`
     * @throws StateConflict `category-name-taken`
     */
    public function execute(Actor $actor, string $name): CategoryView
    {
        $member = $this->access->member($actor);
        $category = Category::named($member->companyId, $name, $this->clock->now());

        $saved = $this->transactions->run(function () use ($category, $member): Category {
            if ($this->categories->nameTaken($member->companyId, $category->name)) {
                throw new StateConflict('Your company already has a category with this name.', 'category-name-taken');
            }

            if ($this->categories->count($member->companyId) >= self::MAX_CATEGORIES) {
                throw new BusinessRuleViolation('A company has at most :max categories.', 'category-limit', parameters: ['max' => self::MAX_CATEGORIES]);
            }

            return $this->categories->add($category);
        });

        return new CategoryView((int) $saved->id, $saved->name, 0);
    }
}
