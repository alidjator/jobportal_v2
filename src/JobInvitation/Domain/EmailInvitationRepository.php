<?php

namespace Src\JobInvitation\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;

interface EmailInvitationRepository
{
    /** @throws StateConflict `already-invited` when an open invitation holds the email for the job */
    public function add(EmailInvitation $invitation): EmailInvitation;

    public function save(EmailInvitation $invitation): void;

    public function find(int $id, bool $forUpdate = false): ?EmailInvitation;

    public function findByTokenHash(string $tokenHash, bool $forUpdate = false): ?EmailInvitation;

    /** An open invitation of the job holds the email (any case). */
    public function openExists(int $jobId, string $email): bool;

    /** How many invitations by email the company has sent since the moment (resends are not counted). */
    public function countOfCompanySince(int $companyId, DateTimeImmutable $since): int;

    /**
     * @return Page<EmailInvitation> the newest first
     */
    public function ofJob(int $jobId, ?EmailInvitationStatus $status, PageRequest $request): Page;

    /** The claimed invitation of this person to the job, when there is one. */
    public function claimedFor(int $jobId, int $userId): ?EmailInvitation;

    /**
     * The invitations that still hold personal data and are due: open ones sent before the moment.
     *
     * @return list<EmailInvitation>
     */
    public function openSentBefore(DateTimeImmutable $before, int $limit): array;

    /**
     * Claimed invitations whose team copy of the CV is older than the moment (the person never chose).
     *
     * @return list<EmailInvitation>
     */
    public function claimedWithCvBefore(DateTimeImmutable $before, int $limit): array;
}
