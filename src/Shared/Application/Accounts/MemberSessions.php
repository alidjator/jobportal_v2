<?php

namespace Src\Shared\Application\Accounts;

/** What the Organization context needs from Identity about the sessions of company members. Provided by Identity. */
interface MemberSessions
{
    /**
     * The company of these accounts became an internal tenant of an ERP group: every token they hold is revoked, in the caller's transaction, so no 30-day token
     * survives the change, and each account is emailed that its next sign-in follows the internal rules (12-hour sessions, two-factor when required).
     *
     * @param  list<int>  $userIds
     */
    public function endForInternalTenant(array $userIds): void;
}
