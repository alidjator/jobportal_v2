<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

interface PsychologistInvitationRepository
{
    /** Inserts a new invitation or updates the acceptance and revocation of an existing one, and returns it (with its id). */
    public function save(PsychologistInvitation $invitation): PsychologistInvitation;

    public function findByTokenHash(string $tokenHash, bool $forUpdate = false): ?PsychologistInvitation;

    public function findOfProvider(int $providerId, int $id, bool $forUpdate = false): ?PsychologistInvitation;

    /**
     * The invitations of the provider that can still be accepted, newest first.
     *
     * @return list<PsychologistInvitation>
     */
    public function pendingOfProvider(int $providerId, DateTimeImmutable $now, bool $forUpdate = false): array;
}
