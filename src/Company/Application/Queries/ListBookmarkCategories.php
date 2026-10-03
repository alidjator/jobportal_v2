<?php

namespace Src\Company\Application\Queries;

use Src\Company\Domain\BookmarkRepository;
use Src\Company\Domain\CompanyRepository;

final class ListBookmarkCategories
{
    public function __construct(private CompanyRepository $companies, private BookmarkRepository $bookmarks) {}

    /** @return list<array<string,mixed>> */
    public function __invoke(int $userId): array
    {
        return $this->bookmarks->categories($this->companies->idForUser($userId));
    }
}
