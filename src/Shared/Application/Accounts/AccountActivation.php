<?php

namespace Src\Shared\Application\Accounts;

/**
 * Which accounts are held: imported from karir.pkp and not signed in since. A held account gets no scheduled email (recommendations, reminders, digests, unread-message
 * mail, job alerts) until its first sign-in; mail caused by a person or a security action and in-app notifications are not held. Provided by Identity.
 */
interface AccountActivation
{
    /**
     * @param  list<int>  $userIds
     * @return list<int> the ids among them that are held
     */
    public function heldAmong(array $userIds): array;
}
