<?php

namespace Src\Company\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Company\Domain\CompanyDirectory;

final class ListCompanies
{
    public function __construct(private CompanyDirectory $directory) {}

    /** @param  array<string,mixed>  $filters */
    public function __invoke(array $filters, int $perPage, ?string $selectedCountry, ?int $viewerUserId): LengthAwarePaginator
    {
        return $this->directory->paginate($filters, $perPage, $selectedCountry, $viewerUserId);
    }
}
