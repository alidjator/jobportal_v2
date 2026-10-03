<?php

namespace Src\Company\Domain;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Pembacaan publik daftar & detail perusahaan. */
interface CompanyDirectory
{
    /**
     * @param  array{keyword?:?string,industry_type?:?string,organization_type?:?string,location?:?string,lat?:mixed,long?:mixed,sortBy?:?string}  $filters
     */
    public function paginate(array $filters, int $perPage, ?string $selectedCountry, ?int $viewerUserId): LengthAwarePaginator;

    /**
     * @return array{companyDetails:array<string,mixed>,user:array<string,mixed>,open_jobs:LengthAwarePaginator}|null
     */
    public function detail(string $username, ?string $selectedCountry, ?int $viewerUserId): ?array;
}
