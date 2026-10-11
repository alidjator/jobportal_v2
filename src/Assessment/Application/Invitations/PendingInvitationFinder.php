<?php

namespace Src\Assessment\Application\Invitations;

use Src\Assessment\Domain\InvitationToken;
use Src\Assessment\Domain\Provider;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistInvitation;
use Src\Assessment\Domain\PsychologistInvitationRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Finds the invitation behind a token and its provider. Whatever is wrong with the token (unknown, expired, revoked, already used) is the
 * same 404, so nobody learns which tokens once existed.
 */
final readonly class PendingInvitationFinder
{
    public function __construct(
        private PsychologistInvitationRepository $invitations,
        private ProviderRepository $providers,
        private Clock $clock,
    ) {}

    /**
     * @return array{PsychologistInvitation, Provider}
     *
     * @throws EntityNotFound `invitation-not-found` when there is no pending invitation for the token
     * @throws AccessDenied `provider-suspended` when the provider is suspended
     */
    public function find(string $plainToken, bool $forUpdate = false): array
    {
        $invitation = strlen($plainToken) === InvitationToken::LENGTH
            ? $this->invitations->findByTokenHash(InvitationToken::fromPlain($plainToken)->hash(), $forUpdate)
            : null;

        if ($invitation === null || ! $invitation->isPending($this->clock->now())) {
            throw new EntityNotFound('Invitation not found.', 'invitation-not-found');
        }

        $provider = $this->providers->findById($invitation->providerId)
            ?? throw new EntityNotFound('Invitation not found.', 'invitation-not-found');

        if (! $provider->isActive()) {
            throw new AccessDenied('This provider is suspended.', 'provider-suspended');
        }

        return [$invitation, $provider];
    }
}
