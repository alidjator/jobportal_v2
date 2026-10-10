<?php

namespace Src\TalentPool\Domain;

use Src\Shared\Domain\Exceptions\StateConflict;

interface CategoryRepository
{
    /** @throws StateConflict `category-name-taken` */
    public function add(Category $category): Category;

    /** The category, only when it belongs to the company. */
    public function find(int $companyId, int $id): ?Category;

    /**
     * Every category of the company by name.
     *
     * @return list<Category>
     */
    public function ofCompany(int $companyId): array;

    public function count(int $companyId): int;

    /** Whether the company has a category with this name, whatever its case, other than the one `$exceptId`. */
    public function nameTaken(int $companyId, string $name, ?int $exceptId = null): bool;

    /** @throws StateConflict `category-name-taken` */
    public function rename(int $companyId, int $id, string $name): void;

    /** Deletes the category of the company; its entries stay, uncategorised. */
    public function delete(int $companyId, int $id): void;
}
