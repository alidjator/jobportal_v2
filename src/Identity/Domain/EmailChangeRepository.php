<?php

namespace Src\Identity\Domain;

interface EmailChangeRepository
{
    public function findByUser(int $userId): ?EmailChange;

    /** Stores the change as the account's only pending one (an earlier one is dropped). */
    public function replace(EmailChange $change): void;

    /** Stores the count of wrong attempts of the pending change. */
    public function saveAttempts(EmailChange $change): void;

    public function delete(int $userId): void;
}
