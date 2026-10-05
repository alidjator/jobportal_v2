<?php

namespace Src\Company\Domain;

use DateTimeImmutable;

interface InvitationRepository
{
    /** Inserts a new invitation or stores the acceptance or revocation of an existing one, and returns it with its id. */
    public function save(Invitation $invitation): Invitation;

    /** Looks an invitation up by the hash of its token. `$forUpdate` locks the row until the transaction ends. */
    public function findByTokenHash(string $tokenHash, bool $forUpdate = false): ?Invitation;

    /** An invitation of the company by its id, whatever its state, or null when the company has no such invitation. `$forUpdate` locks the row until the transaction ends. */
    public function findOfCompany(int $companyId, int $id, bool $forUpdate = false): ?Invitation;

    /**
     * The company's invitations that can still be accepted, newest first. `$forUpdate` locks them until the transaction ends.
     *
     * @return list<Invitation>
     */
    public function pendingOfCompany(int $companyId, DateTimeImmutable $now, bool $forUpdate = false): array;
}
