<?php

namespace Src\Identity\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\AggregateRoot;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A public account: a job seeker, a company member or a psychologist. Administrators are a separate aggregate, see `Admin`. */
final class User extends AggregateRoot
{
    public const int DELETION_GRACE_PERIOD_DAYS = 30;

    public const string ANONYMIZED_NAME = 'Deleted user';

    public function __construct(
        public readonly ?int $id,
        public readonly UserType $type,
        private string $name,
        private string $email,
        private ?string $passwordHash,
        private ?DateTimeImmutable $emailVerifiedAt,
        private AccountStatus $status,
        private ?string $avatarPath,
        private string $locale,
        private ?string $registrationCampaignCode,
        private ?DateTimeImmutable $lastLoginAt,
        private ?DateTimeImmutable $deletionRequestedAt,
        private ?DateTimeImmutable $anonymizedAt,
        private ?DateTimeImmutable $firstLoginAt = null,
    ) {}

    /** A new self-registered job seeker: active, email not yet verified. Company members are created by an admin. */
    public static function registerJobSeeker(
        string $name,
        string $email,
        string $passwordHash,
        string $locale,
        ?string $registrationCampaignCode,
    ): self {
        return new self(null, UserType::JobSeeker, $name, $email, $passwordHash, null, AccountStatus::Active, null, $locale, $registrationCampaignCode, null, null, null);
    }

    /** A new job seeker created by their first Google sign-in: no password, and the email is verified because Google verified it. */
    public static function registerWithGoogle(
        string $name,
        string $email,
        string $locale,
        ?string $registrationCampaignCode,
        DateTimeImmutable $now,
    ): self {
        return new self(null, UserType::JobSeeker, $name, $email, null, $now, AccountStatus::Active, null, $locale, $registrationCampaignCode, null, null, null);
    }

    /** A new company-member account created from an accepted invitation: active, with a verified email, because the invitation proved the owner got it. */
    public static function createCompanyMember(
        string $name,
        string $email,
        string $passwordHash,
        string $locale,
        DateTimeImmutable $now,
    ): self {
        return new self(null, UserType::CompanyMember, $name, $email, $passwordHash, $now, AccountStatus::Active, null, $locale, null, null, null, null);
    }

    /** A new psychologist account created from an accepted invitation: active, with a verified email. Its sign-in always asks for the TOTP second factor. */
    public static function createPsychologist(
        string $name,
        string $email,
        string $passwordHash,
        string $locale,
        DateTimeImmutable $now,
    ): self {
        return new self(null, UserType::Psychologist, $name, $email, $passwordHash, $now, AccountStatus::Active, null, $locale, null, null, null, null);
    }

    public function isPsychologist(): bool
    {
        return $this->type === UserType::Psychologist;
    }

    public function isCompanyMember(): bool
    {
        return $this->type === UserType::CompanyMember;
    }

    /** True for the accounts that sign in at the public endpoints. A psychologist signs in only at the provider endpoints, with a second factor. */
    public function usesPublicSignIn(): bool
    {
        return ! $this->isPsychologist();
    }

    public function name(): string
    {
        return $this->name;
    }

    /** A company admin renames a member of their company; the email stays. */
    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function email(): string
    {
        return $this->email;
    }

    /** Null for accounts that only sign in with a social provider. */
    public function passwordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    public function avatarPath(): ?string
    {
        return $this->avatarPath;
    }

    /** Sets the account's picture to the file at this path, or removes it with null. The caller deletes the file that was replaced. */
    public function changeAvatar(?string $path): void
    {
        $this->avatarPath = $path;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function registrationCampaignCode(): ?string
    {
        return $this->registrationCampaignCode;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function firstLoginAt(): ?DateTimeImmutable
    {
        return $this->firstLoginAt;
    }

    public function deletionRequestedAt(): ?DateTimeImmutable
    {
        return $this->deletionRequestedAt;
    }

    public function anonymizedAt(): ?DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    /** @throws AccessDenied when the account is suspended or deactivated */
    public function assertCanSignIn(): void
    {
        if ($this->isPendingDeletion()) {
            throw new AccessDenied(
                'This account is scheduled for deletion on '.$this->deletionDueAt()?->format('Y-m-d').'. Cancel the deletion to restore it.',
                'account-deactivated',
            );
        }

        $this->status->assertCanSignIn();
    }

    /** Returns true when this is the account's first sign-in. */
    public function recordSignIn(DateTimeImmutable $at): bool
    {
        $first = $this->firstLoginAt === null;
        $this->lastLoginAt = $at;
        $this->firstLoginAt ??= $at;

        return $first;
    }

    public function verifyEmail(DateTimeImmutable $at): void
    {
        $this->emailVerifiedAt ??= $at;
    }

    /** True while the account is deactivated and waiting for the end of the deletion grace period. */
    public function isPendingDeletion(): bool
    {
        return $this->status === AccountStatus::Deactivated && $this->deletionRequestedAt !== null && $this->anonymizedAt === null;
    }

    /** When the scheduled anonymisation may run, or null when no deletion was requested. */
    public function deletionDueAt(): ?DateTimeImmutable
    {
        return $this->deletionRequestedAt?->modify('+'.self::DELETION_GRACE_PERIOD_DAYS.' days');
    }

    public function isDeletionDue(DateTimeImmutable $now): bool
    {
        return $this->isPendingDeletion() && $this->deletionDueAt() <= $now;
    }

    /**
     * Starts the deletion grace period: the account is deactivated until the user cancels or the scheduled anonymisation runs.
     *
     * @throws StateConflict when the account is not active (`deletion-already-requested` or `account-not-active`)
     */
    public function requestDeletion(DateTimeImmutable $now): void
    {
        if ($this->isPendingDeletion()) {
            throw new StateConflict('The deletion of this account was already requested.', 'deletion-already-requested');
        }

        if ($this->status !== AccountStatus::Active) {
            throw new StateConflict('Only an active account can be deleted.', 'account-not-active');
        }

        $this->status = AccountStatus::Deactivated;
        $this->deletionRequestedAt = $now;
    }

    /** Deactivates the account, for example when a company removes its member. An account that is not active is left as it is. */
    public function deactivate(): void
    {
        if ($this->status === AccountStatus::Active) {
            $this->status = AccountStatus::Deactivated;
        }
    }

    /** Lets a deactivated account sign in again (an administrator lifts the suspension of a psychologist). An account waiting for its deletion is left as it is. */
    public function reactivate(): void
    {
        if ($this->status === AccountStatus::Deactivated && ! $this->isPendingDeletion()) {
            $this->status = AccountStatus::Active;
        }
    }

    /** @throws StateConflict when no deletion is pending */
    public function cancelDeletion(): void
    {
        if (! $this->isPendingDeletion()) {
            throw new StateConflict('No deletion of this account is pending.', 'deletion-not-requested');
        }

        $this->status = AccountStatus::Active;
        $this->deletionRequestedAt = null;
    }

    /**
     * Permanently removes the personal data of the account; the row stays so applications and statistics remain intact.
     * Identifying columns are cleared, the email becomes a unique placeholder, and the statistical columns are kept.
     *
     * @throws StateConflict when no deletion is pending
     */
    public function anonymize(DateTimeImmutable $now): void
    {
        if (! $this->isPendingDeletion()) {
            throw new StateConflict('Only an account waiting for its deletion can be anonymised.', 'deletion-not-requested');
        }

        $this->name = self::ANONYMIZED_NAME;
        $this->email = 'anonymized-'.$this->id.'@anonymized.invalid';
        $this->passwordHash = null;
        $this->avatarPath = null;
        $this->emailVerifiedAt = null;
        $this->lastLoginAt = null;
        $this->firstLoginAt = null;
        $this->anonymizedAt = $now;
    }

    /**
     * Whether the password reset flow may be used: active accounts, and accounts waiting for their deletion (so a user who
     * forgot the password can still restore the account). Suspended accounts and accounts deactivated for another reason cannot.
     */
    public function canResetPassword(): bool
    {
        return $this->status === AccountStatus::Active || $this->isPendingDeletion();
    }

    /**
     * Records that Google proved ownership of this account's email address. If the email was never verified, whoever registered
     * the account (possibly not the owner of the address) may know its password, so the password is removed; the owner can set
     * a new one through the reset flow. Returns true when the password was removed, so the caller revokes the existing tokens.
     */
    public function confirmEmailOwnershipThroughGoogle(DateTimeImmutable $now): bool
    {
        $unverified = ! $this->isEmailVerified();

        if ($unverified) {
            $this->passwordHash = null;
        }

        $this->verifyEmail($now);

        return $unverified;
    }

    /** Sets a new password hash. Also used by accounts that so far only signed in with a social provider. */
    public function changePassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }
}
