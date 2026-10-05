<?php

namespace Src\Company\Domain;

use Src\Shared\Domain\AggregateRoot;

/**
 * An employer. It only holds what exists from the start (name, slug, status and the two required classifications); the rest of the
 * profile is added when the profile is written. A company is created by an admin, never by self-registration.
 */
final class Company extends AggregateRoot
{
    public function __construct(
        public readonly ?int $id,
        private string $name,
        private string $slug,
        private CompanyStatus $status,
        private int $industryId,
        private int $organizationTypeId,
        private ?int $createdByAdminId,
    ) {}

    public static function create(string $name, string $slug, int $industryId, int $organizationTypeId, int $createdByAdminId): self
    {
        return new self(null, $name, $slug, CompanyStatus::Active, $industryId, $organizationTypeId, $createdByAdminId);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function status(): CompanyStatus
    {
        return $this->status;
    }

    public function industryId(): int
    {
        return $this->industryId;
    }

    public function organizationTypeId(): int
    {
        return $this->organizationTypeId;
    }

    public function createdByAdminId(): ?int
    {
        return $this->createdByAdminId;
    }

    public function isActive(): bool
    {
        return $this->status === CompanyStatus::Active;
    }
}
