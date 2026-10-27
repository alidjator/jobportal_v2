<?php

namespace Src\Assessment\Application\Team;

use Src\Assessment\Domain\PsychologistInvitation;
use Src\Assessment\Domain\PsychologistInvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;

final readonly class ListTeamInvitations
{
    public function __construct(
        private TeamAccess $access,
        private PsychologistInvitationRepository $invitations,
        private Clock $clock,
    ) {}

    /**
     * @return list<PsychologistInvitation>
     *
     * @throws AccessDenied `not-a-lead` and the refusals of `TeamAccess`
     */
    public function execute(Actor $actor): array
    {
        $lead = $this->access->lead($actor);

        return $this->invitations->pendingOfProvider($lead->providerId, $this->clock->now());
    }
}
