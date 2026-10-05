<?php

namespace Src\Shared\Application\Accounts;

/**
 * What the Company context needs from Identity: company-member accounts. Provided by Identity, so Company never touches the account
 * tables, and a job-seeker account is never turned into a company-member account.
 */
interface CompanyMemberAccounts
{
    /** True when any account, of any type, already uses the email address. The address is compared case-insensitively. */
    public function emailIsRegistered(string $email): bool;

    /**
     * Creates an active company-member account whose email counts as verified (the invitation proved the owner got the email).
     * Runs inside the caller's transaction.
     *
     * @return int the id of the new account
     */
    public function register(string $name, string $email, string $password, string $locale): int;

    /** Signs the account in: issues a 30-day token and announces the sign-in. Call it after the transaction that created the account has committed. */
    public function signIn(int $userId, string $deviceName, ?string $ipAddress, ?string $userAgent): AccountSession;

    /**
     * Deactivates the account (it can no longer sign in) and revokes every token it holds. The account is kept, not anonymised, so what it
     * did stays attributed to its name. Runs inside the caller's transaction.
     */
    public function deactivate(int $userId): void;

    /**
     * Name, email, status and last sign-in of these accounts, keyed by account id; an id without an account is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, MemberAccountSummary>
     */
    public function summaries(array $userIds): array;
}
