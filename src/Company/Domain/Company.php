<?php

namespace Src\Company\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\AggregateRoot;

/**
 * An employer. A company is created by an admin, never by self-registration, with the required minimum of its profile; the company admin
 * completes the rest. Logo and banner come with the file upload feature.
 */
final class Company extends AggregateRoot
{
    public function __construct(
        public readonly ?int $id,
        private string $slug,
        private CompanyStatus $status,
        private ?DateTimeImmutable $verifiedAt,
        private ?int $createdByAdminId,
        private CompanyProfile $profile,
    ) {}

    public static function create(string $name, string $slug, int $industryId, int $organizationTypeId, int $createdByAdminId): self
    {
        return new self(null, $slug, CompanyStatus::Active, null, $createdByAdminId, CompanyProfile::minimal($name, $industryId, $organizationTypeId));
    }

    public function name(): string
    {
        return $this->profile->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function status(): CompanyStatus
    {
        return $this->status;
    }

    public function verifiedAt(): ?DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function profile(): CompanyProfile
    {
        return $this->profile;
    }

    public function industryId(): int
    {
        return $this->profile->industryId;
    }

    public function organizationTypeId(): int
    {
        return $this->profile->organizationTypeId;
    }

    public function createdByAdminId(): ?int
    {
        return $this->createdByAdminId;
    }

    public function isActive(): bool
    {
        return $this->status === CompanyStatus::Active;
    }

    /** Whether the public may see this company: it is active and its admin has not hidden it. */
    public function isPublic(): bool
    {
        return $this->isActive() && $this->profile->isVisible;
    }

    public function changeProfile(CompanyProfile $profile): void
    {
        $this->profile = $profile;
    }

    public function changeSlug(string $slug): void
    {
        $this->slug = $slug;
    }
}
