<?php

namespace Src\Company\Application;

final readonly class CreateCompanyCommand
{
    public function __construct(
        public string $name,
        public int $industryId,
        public int $organizationTypeId,
        public string $adminEmail,
        public string $language,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
