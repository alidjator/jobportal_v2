<?php

namespace Src\Shared\Application\Company;

/**
 * Which accounts are company members whose membership a company admin suspended. A suspended member gets no notification and no email until they are
 * reactivated, so Notification asks before it writes or lets a context send. Provided by Company.
 */
interface SuspendedMembers
{
    /**
     * @param  list<int>  $userIds
     * @return list<int> the ids among them that are suspended
     */
    public function among(array $userIds): array;
}
