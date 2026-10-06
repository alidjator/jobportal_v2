<?php

namespace Src\Shared\Application\Company;

/** What another context may show about a company: who it is and whether it is suspended (`active` or `suspended`). */
final readonly class CompanySummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $status,
    ) {}
}
