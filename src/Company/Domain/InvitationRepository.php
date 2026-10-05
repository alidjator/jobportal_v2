<?php

namespace Src\Company\Domain;

interface InvitationRepository
{
    /** Inserts a new invitation or stores the acceptance of an existing one, and returns it with its id. */
    public function save(Invitation $invitation): Invitation;

    /** Looks an invitation up by the hash of its token. `$forUpdate` locks the row until the transaction ends. */
    public function findByTokenHash(string $tokenHash, bool $forUpdate = false): ?Invitation;
}
