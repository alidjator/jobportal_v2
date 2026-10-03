<?php

namespace Src\Company\Application\Commands;

use Src\Company\Domain\BookmarkRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class UpdateBookmarkCategory
{
    public function __construct(private CompanyRepository $companies, private BookmarkRepository $bookmarks) {}

    /** @return array{category:array<string,mixed>,message:string} */
    public function __invoke(int $userId, int $categoryId, string $name): array
    {
        $companyId = $this->companies->idForUser($userId);

        if (! $this->bookmarks->categoryExists($companyId, $categoryId)) {
            throw new EntityNotFound('Category not found');
        }

        return ['category' => $this->bookmarks->updateCategory($categoryId, $name), 'message' => 'Category updated successfully'];
    }
}
