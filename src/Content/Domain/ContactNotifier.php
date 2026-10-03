<?php

namespace Src\Content\Domain;

interface ContactNotifier
{
    /**
     * Beritahu admin lewat email (best-effort: tidak boleh melempar exception).
     *
     * @param  array{name: string, email: string, subject: string, message: string}  $contact
     */
    public function notifyAdmins(array $contact): void;
}
