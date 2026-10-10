<?php

namespace Src\LegacyImport\Application;

/** Writes subscribers into the table of the Newsletter context (the documented exception of this context). */
interface NewsletterSubscriberWriter
{
    /** Whether an address is already on the list, whatever its status (compared as given: it is lower case). */
    public function emailTaken(string $email): bool;

    /**
     * Inserts a confirmed subscriber. The address is already lower case and `$locale` is `id` or `en`; times are `Y-m-d H:i:s` UTC.
     *
     * @return int the id of the new subscriber
     */
    public function insertConfirmed(string $email, string $locale, string $confirmedAt, string $updatedAt): int;
}
