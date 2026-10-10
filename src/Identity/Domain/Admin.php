<?php

namespace Src\Identity\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\AggregateRoot;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** A back-office administrator. Admins have their own table, guard and permissions (RBAC). */
final class Admin extends AggregateRoot
{
    public function __construct(
        public readonly ?int $id,
        private string $name,
        private string $email,
        private string $passwordHash,
        private ?DateTimeImmutable $emailVerifiedAt,
        private AccountStatus $status,
        private ?string $avatarPath,
        private ?DateTimeImmutable $lastLoginAt,
    ) {}

    /** A new active administrator. The email counts as verified: admins are created by an operator, not self-registered. */
    public static function create(string $name, string $email, string $passwordHash, DateTimeImmutable $now): self
    {
        return new self(null, $name, $email, $passwordHash, $now, AccountStatus::Active, null, null);
    }

    public function avatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    /** @throws AccessDenied when the account is suspended or deactivated */
    public function assertCanSignIn(): void
    {
        $this->status->assertCanSignIn();
    }

    public function recordSignIn(DateTimeImmutable $at): void
    {
        $this->lastLoginAt = $at;
    }

    public function changePassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }
}
