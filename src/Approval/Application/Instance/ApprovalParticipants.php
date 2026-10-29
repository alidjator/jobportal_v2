<?php

namespace Src\Approval\Application\Instance;

use Src\Approval\Domain\ApprovalInstanceRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Approval\ApprovalParticipation;

/** Whether an account takes part in approvals (the menu of the approval pages follows it): it asked for one or holds, or held, a place in one, or one waits for it now. */
final readonly class ApprovalParticipants implements ApprovalParticipation
{
    public function __construct(private ApprovalInstanceRepository $instances, private MyApprovals $mine) {}

    public function takesPart(Actor $actor): bool
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->id === null) {
            return false;
        }

        return $this->instances->involves($actor->id) || $this->mine->waitingCount($actor) > 0;
    }
}
