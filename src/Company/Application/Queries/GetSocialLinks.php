<?php

namespace Src\Company\Application\Queries;

use Src\Company\Domain\CompanyRepository;

final class GetSocialLinks
{
    public function __construct(private CompanyRepository $companies) {}

    /** @return array{social_media:list<array{id:int,social_media:string,url:string}>} */
    public function __invoke(int $userId): array
    {
        return ['social_media' => $this->companies->socialLinks($userId)];
    }
}
