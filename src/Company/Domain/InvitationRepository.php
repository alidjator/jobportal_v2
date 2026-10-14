<?php

namespace Src\Company\Domain;

use DateTimeImmutable;

interface InvitationRepository
{
    /** Inserts a new invitation or stores the changes (acceptance, revocation, edit, resend) of an existing one, and returns it with its id. */
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

    /**
     * The invitations the team list shows: the pending ones and the ones that expired less than 30 days ago, never an accepted or revoked one. Newest first.
     *
     * @return list<Invitation>
     */
    public function listedOfCompany(int $companyId, DateTimeImmutable $now): array;

    /** Deletes the invitations that ended (expired, revoked or accepted) more than 30 days ago, and returns how many. */
    public function purgeEnded(DateTimeImmutable $now): int;
}
