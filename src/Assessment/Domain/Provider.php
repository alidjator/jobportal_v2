<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/** A partner institution that owns psychological batteries and employs the psychologists who sign their reports. Registered by a platform admin. */
final class Provider
{
    public function __construct(
        public readonly ?int $id,
        private string $code,
        private string $name,
        private string $legalName,
        private ProviderStatus $status,
        public readonly ?DateTimeImmutable $createdAt = null,
        private bool $autoIssueAllowed = false,
        private ProviderIdentity $identity = new ProviderIdentity,
    ) {}

    public static function register(string $code, string $name, string $legalName): self
    {
        return new self(null, $code, $name, $legalName, ProviderStatus::Active);
    }

    /** The short code that opens the report numbers: 2 to 10 uppercase letters or digits. */
    public function code(): string
    {
        return $this->code;
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

    /** Whether the provider's batteries may issue their reports automatically; only a platform admin allows it. */
    public function autoIssueAllowed(): bool
    {
        return $this->autoIssueAllowed;
    }

    public function allowAutoIssue(bool $allowed): void
    {
        $this->autoIssueAllowed = $allowed;
    }

    public function identity(): ProviderIdentity
    {
        return $this->identity;
    }

    public function changeIdentity(ProviderIdentity $identity): void
    {
        $this->identity = $identity;
    }

    public function rename(string $code, string $name, string $legalName): void
    {
        $this->code = $code;
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
