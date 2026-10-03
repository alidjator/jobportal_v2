<?php

namespace Src\Company\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Company\Domain\BookmarkRepository;
use Src\Company\Domain\CompanyRepository;

final class ListBookmarkedCandidates
{
    private const PER_PAGE = 12;

    public function __construct(private CompanyRepository $companies, private BookmarkRepository $bookmarks) {}

    public function __invoke(int $userId, ?int $categoryId): LengthAwarePaginator
    {
        return $this->bookmarks->paginateCandidates($this->companies->idForUser($userId), $categoryId, self::PER_PAGE);
    }
}
