<?php

namespace Src\Company\Application\Commands;

use Src\Company\Domain\BookmarkRepository;
use Src\Company\Domain\CompanyRepository;

final class CreateBookmarkCategory
{
    public function __construct(private CompanyRepository $companies, private BookmarkRepository $bookmarks) {}

    /** @return array{category:array<string,mixed>,message:string} */
    public function __invoke(int $userId, string $name): array
    {
        $category = $this->bookmarks->createCategory($this->companies->idForUser($userId), $name);

        return ['category' => $category, 'message' => 'Category created successfully'];
    }
}
