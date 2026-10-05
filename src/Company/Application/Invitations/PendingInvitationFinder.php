<?php

namespace Src\Company\Application\Invitations;

use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Invitation;
use Src\Company\Domain\InvitationRepository;
use Src\Company\Domain\InvitationToken;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Finds the invitation behind a token and its company. Whatever is wrong with the token (unknown, expired, revoked, already used) is the
 * same 404, so nobody learns which tokens once existed.
 */
final readonly class PendingInvitationFinder
{
    public function __construct(
        private InvitationRepository $invitations,
        private CompanyRepository $companies,
        private Clock $clock,
    ) {}

    /**
     * @return array{Invitation, Company}
     *
     * @throws EntityNotFound `invitation-not-found` when there is no pending invitation for the token
     * @throws AccessDenied `company-suspended` when the company is suspended
     */
    public function find(string $plainToken, bool $forUpdate = false): array
    {
        $invitation = strlen($plainToken) === InvitationToken::LENGTH
            ? $this->invitations->findByTokenHash(InvitationToken::fromPlain($plainToken)->hash(), $forUpdate)
            : null;

        if ($invitation === null || ! $invitation->isPending($this->clock->now())) {
            throw new EntityNotFound('Invitation not found.', 'invitation-not-found');
        }

        $company = $this->companies->findById($invitation->companyId)
            ?? throw new EntityNotFound('Invitation not found.', 'invitation-not-found');

        if (! $company->isActive()) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return [$invitation, $company];
    }
}
