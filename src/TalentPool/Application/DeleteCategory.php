<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\TalentPool\Domain\CategoryRepository;

/** A company admin deletes a category of their company; its candidates stay in the pool, uncategorised. Deleting one that does not exist changes nothing. */
final readonly class DeleteCategory
{
    public function __construct(private TalentPoolAccess $access, private CategoryRepository $categories) {}

    /** @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required` */
    public function execute(Actor $actor, int $id): void
    {
        $this->categories->delete($this->access->admin($actor)->companyId, $id);
    }
}
