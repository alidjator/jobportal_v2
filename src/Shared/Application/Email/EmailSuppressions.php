<?php

namespace Src\Shared\Application\Email;

/**
 * The addresses the platform no longer sends non-essential email to (hard bounces, complaints, manual entries), kept by the Platform context.
 * Other contexts read it to tell a person that their address does not receive email, and to skip such addresses in bulk runs.
 */
interface EmailSuppressions
{
    /** Why the address is suppressed (`hard_bounce`, `complaint` or `manual`), or null when it is not. */
    public function reason(string $email): ?string;

    /**
     * The suppressed addresses among these, lower case, each with its reason.
     *
     * @param  list<string>  $emails
     * @return array<string, string>
     */
    public function reasons(array $emails): array;
}
