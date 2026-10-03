<?php

namespace Src\Company\Infrastructure;

use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Contracts\UserProfileProjection;

/** Profil perusahaan untuk respons login/user-info (Identity). */
class CompanyProfileProjection implements UserProfileProjection
{
    public function __construct(private CompanyRepository $companies) {}

    public function role(): string
    {
        return 'company';
    }

    public function project(int $userId): array
    {
        return $this->companies->profile($userId);
    }
}
