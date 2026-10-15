<?php

namespace Src\Shared\Application\Accounts;

/**
 * What the Company context needs from Identity about the second factor (TOTP) of company-member accounts. Provided by Identity, so Company never touches
 * the factor tables.
 */
interface MemberTwoFactor
{
    /**
     * Which of these accounts have a confirmed second factor.
     *
     * @param  list<int>  $userIds
     * @return array<int, bool> keyed by account id
     */
    public function confirmed(array $userIds): array;

    /**
     * The second factor became required for these accounts. Each one without a confirmed factor loses every token it holds, in the caller's transaction,
     * and is emailed that the next sign-in asks for enrolment; an account that already has a factor keeps its tokens.
     *
     * @param  list<int>  $userIds
     * @return list<int> the accounts whose sessions ended
     */
    public function requireFor(array $userIds): array;

    /** Removes the factor and the recovery codes of the account and revokes its tokens (a lost device); the member enrols again at the next sign-in. Runs inside the caller's transaction. */
    public function reset(int $userId): void;
}
