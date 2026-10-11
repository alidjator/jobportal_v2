<?php

namespace Src\Shared\Application\Accounts;

/**
 * What the Assessment context needs from Identity: psychologist accounts. Provided by Identity, so Assessment never touches the account
 * tables. A psychologist signs in only at the provider endpoints, with a TOTP second factor.
 */
interface PsychologistAccounts
{
    /** True when any account, of any type, already uses the email address. The address is compared case-insensitively. */
    public function emailIsRegistered(string $email): bool;

    /**
     * Creates an active psychologist account whose email counts as verified (the invitation proved the owner got the email). Runs inside the
     * caller's transaction.
     *
     * @return int the id of the new account
     */
    public function register(string $name, string $email, string $password, string $locale): int;

    /** Deactivates the account (it can no longer sign in) and revokes every token it holds. Runs inside the caller's transaction. */
    public function deactivate(int $userId): void;

    /** Lets a deactivated account sign in again. Runs inside the caller's transaction. */
    public function reactivate(int $userId): void;

    /** Removes the second factor of the account (a lost device) and revokes its tokens; the psychologist enrols again at the next sign-in. */
    public function resetTwoFactor(int $userId): void;

    /**
     * Name, email, status and last sign-in of these accounts, keyed by account id; an id without an account is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, MemberAccountSummary>
     */
    public function summaries(array $userIds): array;
}
