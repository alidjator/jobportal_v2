<?php

namespace Src\Shared\Application;

use Src\Shared\Application\Company\CompanyMembership;
use Src\Shared\Application\Company\CompanyPermission;

/**
 * Who performs a use case. Controllers build it from the authenticated account and pass it explicitly; there is no
 * ambient current user in the Application layer. Queue jobs and console commands build their own (for example the
 * system actor). A company member also carries their company and role (`membership`), filled in when the HTTP layer
 * builds the actor; it is null for every other kind, and for a company member who belongs to no company. `supportGroupId` names the group whose support access a
 * platform admin acts under (`docs/design/erp-governance.md` section 1.1).
 */
final readonly class Actor
{
    private function __construct(
        public ActorKind $kind,
        public ?int $id,
        public ?CompanyMembership $membership = null,
        public ?int $supportGroupId = null,
    ) {}

    public function withMembership(?CompanyMembership $membership): self
    {
        return new self($this->kind, $this->id, $membership, $this->supportGroupId);
    }

    /** A platform admin acting under a group's temporary support access: read only, and only while the group's grant is active. */
    public function forSupport(int $groupId): self
    {
        return new self($this->kind, $this->id, $this->membership, $groupId);
    }

    public function isCompanyAdmin(): bool
    {
        return $this->membership?->isCompanyAdmin() ?? false;
    }

    /** Whether a company member holds the permission; a company admin holds every one, anyone without a membership none. */
    public function can(CompanyPermission $permission): bool
    {
        return $this->membership?->can($permission) ?? false;
    }

    public static function jobSeeker(int $id): self
    {
        return new self(ActorKind::JobSeeker, $id);
    }

    public static function companyMember(int $id): self
    {
        return new self(ActorKind::CompanyMember, $id);
    }

    public static function psychologist(int $id): self
    {
        return new self(ActorKind::Psychologist, $id);
    }

    public static function admin(int $id): self
    {
        return new self(ActorKind::Admin, $id);
    }

    public static function system(): self
    {
        return new self(ActorKind::System, null);
    }

    public function isAdmin(): bool
    {
        return $this->kind === ActorKind::Admin;
    }

    public function isSystem(): bool
    {
        return $this->kind === ActorKind::System;
    }
}
