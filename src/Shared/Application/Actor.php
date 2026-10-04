<?php

namespace Src\Shared\Application;

/**
 * Who performs a use case. Controllers build it from the authenticated account and pass it explicitly; there is no
 * ambient current user in the Application layer. Queue jobs and console commands build their own (for example the
 * system actor). Company membership and role are added with the Company context.
 */
final readonly class Actor
{
    private function __construct(
        public ActorKind $kind,
        public ?int $id,
    ) {}

    public static function jobSeeker(int $id): self
    {
        return new self(ActorKind::JobSeeker, $id);
    }

    public static function companyMember(int $id): self
    {
        return new self(ActorKind::CompanyMember, $id);
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
