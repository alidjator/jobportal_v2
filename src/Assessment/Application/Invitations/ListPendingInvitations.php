<?php

namespace Src\Assessment\Application\Invitations;

use Src\Assessment\Application\Admin\AdminOnly;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistInvitation;
use Src\Assessment\Domain\PsychologistInvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class ListPendingInvitations
{
    public function __construct(
        private ProviderRepository $providers,
        private PsychologistInvitationRepository $invitations,
        private Clock $clock,
    ) {}

    /**
     * @return list<PsychologistInvitation>
     *
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the provider does not exist
     */
    public function execute(Actor $actor, int $providerId): array
    {
        AdminOnly::assert($actor, 'list invitations');

        $this->providers->findById($providerId) ?? throw EntityNotFound::of('Provider', $providerId);

        return $this->invitations->pendingOfProvider($providerId, $this->clock->now());
    }
}
