<?php

namespace Src\Identity\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\AggregateRoot;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\StateConflict;

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
        private ?string $twoFactorSecret = null,
        private ?DateTimeImmutable $twoFactorConfirmedAt = null,
        private ?int $twoFactorLastStep = null,
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

    /** The second factor counts only once the first code was confirmed. */
    public function hasTwoFactor(): bool
    {
        return $this->twoFactorSecret !== null && $this->twoFactorConfirmedAt !== null;
    }

    public function twoFactorSecret(): ?string
    {
        return $this->twoFactorSecret;
    }

    public function twoFactorConfirmedAt(): ?DateTimeImmutable
    {
        return $this->twoFactorConfirmedAt;
    }

    public function twoFactorLastStep(): ?int
    {
        return $this->twoFactorLastStep;
    }

    /** Stores a fresh secret for an admin who has not confirmed a second factor yet; a pending secret is replaced. */
    public function beginTwoFactorEnrolment(string $secret): void
    {
        if ($this->hasTwoFactor()) {
            throw new StateConflict('This administrator already has a second factor.', 'two-factor-already-enabled');
        }

        $this->twoFactorSecret = $secret;
        $this->twoFactorLastStep = null;
    }

    public function confirmTwoFactor(DateTimeImmutable $at, int $step): void
    {
        $this->twoFactorConfirmedAt = $at;
        $this->twoFactorLastStep = $step;
    }

    /** Remembers the accepted time step so the same code cannot be used again. */
    public function recordTwoFactorStep(int $step): void
    {
        $this->twoFactorLastStep = $step;
    }

    /** Removes the second factor (a lost device); the admin enrols again at the next sign-in. */
    public function resetTwoFactor(): void
    {
        $this->twoFactorSecret = null;
        $this->twoFactorConfirmedAt = null;
        $this->twoFactorLastStep = null;
    }
}
