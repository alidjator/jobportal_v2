<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\TestOwner;

/**
 * Who is writing tests: a company admin (for the company), a psychologist (for the provider) or a platform admin (for the system library).
 * Exactly one of `userId` and `adminId` is set; it is stored as the creator of a new test.
 */
final readonly class Author
{
    public function __construct(
        public TestOwner $owner,
        public ?int $userId,
        public ?int $adminId,
    ) {}

    /** The company whose limits apply: the author's own company, none (the global values) for a provider or the library. */
    public function companyId(): ?int
    {
        return $this->owner->companyId;
    }
}
