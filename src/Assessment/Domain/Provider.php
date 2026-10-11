<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/** A partner institution that owns psychological batteries and employs the psychologists who sign their reports. Registered by a platform admin. */
final class Provider
{
    public function __construct(
        public readonly ?int $id,
        private string $name,
        private string $legalName,
        private ProviderStatus $status,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function register(string $name, string $legalName): self
    {
        return new self(null, $name, $legalName, ProviderStatus::Active);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function legalName(): string
    {
        return $this->legalName;
    }

    public function status(): ProviderStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function rename(string $name, string $legalName): void
    {
        $this->name = $name;
        $this->legalName = $legalName;
    }

    public function suspend(): void
    {
        $this->status = ProviderStatus::Suspended;
    }

    public function restore(): void
    {
        $this->status = ProviderStatus::Active;
    }
}
