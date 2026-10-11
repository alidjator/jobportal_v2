<?php

namespace Src\Assessment\Domain;

/** Who owns a test: one company (its skill tests), one provider (its psychological batteries) or nobody (the system library of skill tests). */
final readonly class TestOwner
{
    private function __construct(public ?int $companyId, public ?int $providerId) {}

    public static function company(int $companyId): self
    {
        return new self($companyId, null);
    }

    public static function provider(int $providerId): self
    {
        return new self(null, $providerId);
    }

    public static function library(): self
    {
        return new self(null, null);
    }

    public function kind(): TestKind
    {
        return $this->providerId === null ? TestKind::Skill : TestKind::Psychological;
    }

    public function isLibrary(): bool
    {
        return $this->companyId === null && $this->providerId === null;
    }

    public function equals(self $other): bool
    {
        return $this->companyId === $other->companyId && $this->providerId === $other->providerId;
    }
}
