<?php

namespace Src\Shared\Application\Company;

/**
 * What anyone may see of a company next to something it published (a job): who it is, whether it holds the verification badge and where its logo is. It holds
 * no contact data. `logoUrl` is the absolute public URL, ending in a version so a replaced logo breaks the cache; null when there is no logo.
 */
final readonly class PublicCompanyCard
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public bool $verified,
        public ?string $logoUrl,
    ) {}
}
