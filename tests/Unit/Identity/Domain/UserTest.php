<?php

namespace Tests\Unit\Identity\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Identity\Domain\AccountStatus;
use Src\Identity\Domain\Admin;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserType;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\StateConflict;

final class UserTest extends TestCase
{
    private function user(AccountStatus $status = AccountStatus::Active, ?DateTimeImmutable $deletionRequestedAt = null): User
    {
        return new User(1, UserType::JobSeeker, 'Siti', 'siti@example.test', 'hash', null, $status, null, 'id', null, null, $deletionRequestedAt, null);
    }

    public function test_an_active_account_can_sign_in(): void
    {
        $this->user()->assertCanSignIn();

        $this->addToAssertionCount(1);
    }

    public function test_a_suspended_account_is_refused_with_its_own_code(): void
    {
        try {
            $this->user(AccountStatus::Suspended)->assertCanSignIn();
            $this->fail('Sign-in must be refused.');
        } catch (AccessDenied $e) {
            $this->assertSame('account-suspended', $e->errorCode());
        }
    }

    public function test_a_deactivated_account_is_refused_with_its_own_code(): void
    {
        try {
            $this->user(AccountStatus::Deactivated)->assertCanSignIn();
            $this->fail('Sign-in must be refused.');
        } catch (AccessDenied $e) {
            $this->assertSame('account-deactivated', $e->errorCode());
        }
    }

    public function test_the_refusal_during_the_grace_period_names_the_deletion_date(): void
    {
        $user = $this->user(AccountStatus::Deactivated, new DateTimeImmutable('2026-10-03 08:00:00'));

        try {
            $user->assertCanSignIn();
            $this->fail('Sign-in must be refused.');
        } catch (AccessDenied $e) {
            $this->assertSame('account-deactivated', $e->errorCode());
            $this->assertStringContainsString('2026-11-02', $e->getMessage());
        }
    }

    public function test_password_reset_is_allowed_for_active_accounts_and_accounts_waiting_for_deletion_only(): void
    {
        $requested = new DateTimeImmutable('2026-10-03 08:00:00');

        $this->assertTrue($this->user()->canResetPassword());
        $this->assertTrue($this->user(AccountStatus::Deactivated, $requested)->canResetPassword());
        $this->assertFalse($this->user(AccountStatus::Deactivated)->canResetPassword());
        $this->assertFalse($this->user(AccountStatus::Suspended)->canResetPassword());
        $this->assertFalse($this->user(AccountStatus::Suspended, $requested)->canResetPassword());
    }

    public function test_an_anonymised_account_cannot_reset_its_password(): void
    {
        $user = new User(1, UserType::JobSeeker, 'Anonymous', 'anonymized-1@anonymized.invalid', null, null, AccountStatus::Deactivated, null, 'id', null, null, new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-10-01'));

        $this->assertFalse($user->canResetPassword());
    }

    public function test_requesting_deletion_deactivates_the_account_and_sets_the_due_date(): void
    {
        $user = $this->user();
        $now = new DateTimeImmutable('2026-10-03 08:00:00');

        $user->requestDeletion($now);

        $this->assertSame(AccountStatus::Deactivated, $user->status());
        $this->assertTrue($user->isPendingDeletion());
        $this->assertSame('2026-11-02 08:00:00', $user->deletionDueAt()?->format('Y-m-d H:i:s'));
        $this->assertFalse($user->isDeletionDue(new DateTimeImmutable('2026-11-02 07:59:59')));
        $this->assertTrue($user->isDeletionDue(new DateTimeImmutable('2026-11-02 08:00:00')));
    }

    public function test_deletion_cannot_be_requested_twice_or_for_a_suspended_account(): void
    {
        $pending = $this->user(AccountStatus::Deactivated, new DateTimeImmutable('2026-10-01'));

        try {
            $pending->requestDeletion(new DateTimeImmutable('2026-10-03'));
            $this->fail('A second request must be refused.');
        } catch (StateConflict $e) {
            $this->assertSame('deletion-already-requested', $e->errorCode());
        }

        try {
            $this->user(AccountStatus::Suspended)->requestDeletion(new DateTimeImmutable('2026-10-03'));
            $this->fail('A suspended account cannot request deletion.');
        } catch (StateConflict $e) {
            $this->assertSame('account-not-active', $e->errorCode());
        }
    }

    public function test_cancelling_restores_the_account_and_needs_a_pending_deletion(): void
    {
        $user = $this->user(AccountStatus::Deactivated, new DateTimeImmutable('2026-10-01'));

        $user->cancelDeletion();

        $this->assertSame(AccountStatus::Active, $user->status());
        $this->assertNull($user->deletionRequestedAt());

        $this->expectException(StateConflict::class);
        $user->cancelDeletion();
    }

    public function test_anonymising_clears_personal_data_and_keeps_the_statistical_columns(): void
    {
        $user = new User(7, UserType::CompanyMember, 'Siti Aminah', 'siti@example.test', 'hash', new DateTimeImmutable('2026-01-01'), AccountStatus::Deactivated, 'avatars/siti.jpg', 'en', 'jobfair-2026', new DateTimeImmutable('2026-09-30'), new DateTimeImmutable('2026-09-01'), null);
        $now = new DateTimeImmutable('2026-10-03 02:00:00');

        $user->anonymize($now);

        $this->assertSame(User::ANONYMIZED_NAME, $user->name());
        $this->assertSame('anonymized-7@anonymized.invalid', $user->email());
        $this->assertNull($user->passwordHash());
        $this->assertNull($user->avatarPath());
        $this->assertNull($user->emailVerifiedAt());
        $this->assertNull($user->lastLoginAt());
        $this->assertSame($now, $user->anonymizedAt());
        $this->assertSame(UserType::CompanyMember, $user->type);
        $this->assertSame('en', $user->locale());
        $this->assertSame('jobfair-2026', $user->registrationCampaignCode());
        $this->assertSame(AccountStatus::Deactivated, $user->status());
        $this->assertNotNull($user->deletionRequestedAt());
        $this->assertFalse($user->isPendingDeletion());
    }

    public function test_only_an_account_waiting_for_deletion_can_be_anonymised(): void
    {
        $this->expectException(StateConflict::class);

        $this->user()->anonymize(new DateTimeImmutable('2026-10-03'));
    }

    public function test_recording_a_sign_in_and_verifying_the_email(): void
    {
        $user = $this->user();
        $first = new DateTimeImmutable('2026-10-03 09:00:00');

        $this->assertFalse($user->isEmailVerified());

        $user->recordSignIn($first);
        $user->verifyEmail($first);
        $user->verifyEmail(new DateTimeImmutable('2026-10-04 09:00:00'));

        $this->assertSame($first, $user->lastLoginAt());
        $this->assertTrue($user->isEmailVerified());
        $this->assertSame($first, $user->emailVerifiedAt());
    }

    public function test_an_admin_follows_the_same_sign_in_rule(): void
    {
        $admin = new Admin(1, 'Root', 'root@example.test', 'hash', null, AccountStatus::Suspended, null, null);

        $this->expectException(AccessDenied::class);

        $admin->assertCanSignIn();
    }
}
