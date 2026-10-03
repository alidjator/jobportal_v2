<?php

namespace Src\Company\Application\Queries;

use Src\Company\Domain\CompanyDirectory;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class GetCompanyDetails
{
    public function __construct(private CompanyDirectory $directory) {}

    /** @return array<string,mixed> */
    public function __invoke(string $username, ?string $selectedCountry, ?int $viewerUserId): array
    {
        return $this->directory->detail($username, $selectedCountry, $viewerUserId)
            ?? throw new EntityNotFound('User not found');
    }
}
