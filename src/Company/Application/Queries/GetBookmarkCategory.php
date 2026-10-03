<?php

namespace Src\Company\Application\Queries;

use Src\Company\Domain\BookmarkRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class GetBookmarkCategory
{
    public function __construct(private CompanyRepository $companies, private BookmarkRepository $bookmarks) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId, int $categoryId): array
    {
        return $this->bookmarks->category($this->companies->idForUser($userId), $categoryId)
            ?? throw new EntityNotFound('Category not found');
    }
}
