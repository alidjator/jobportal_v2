<?php

namespace Src\Assessment\Application\Team;

use Src\Assessment\Application\Review\ReviewAccess;
use Src\Assessment\Domain\Psychologist;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The check every team action starts with: the actor is an active psychologist of an active institution, and a lead of it. */
final readonly class TeamAccess
{
    public function __construct(private ReviewAccess $access) {}

    /** @throws AccessDenied `not-a-psychologist`, `psychologist-suspended`, `provider-suspended` or `not-a-lead` */
    public function lead(Actor $actor): Psychologist
    {
        $psychologist = $this->access->psychologist($actor);

        if (! $psychologist->isLead()) {
            throw new AccessDenied('Only a lead of the institution can manage its team.', 'not-a-lead');
        }

        return $psychologist;
    }
}
