<?php

namespace Src\Company\Application\Commands;

use Src\Company\Domain\BookmarkRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class DeleteBookmarkCategory
{
    public function __construct(private CompanyRepository $companies, private BookmarkRepository $bookmarks) {}

    public function __invoke(int $userId, int $categoryId): string
    {
        $companyId = $this->companies->idForUser($userId);

        if (! $this->bookmarks->categoryExists($companyId, $categoryId)) {
            throw new EntityNotFound('Category not found');
        }

        $this->bookmarks->deleteCategory($categoryId);

        return 'Category deleted successfully';
    }
}
