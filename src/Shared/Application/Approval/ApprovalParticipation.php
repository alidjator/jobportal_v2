<?php

namespace Src\Shared\Application\Approval;

use Src\Shared\Application\Actor;

/** Whether a company member takes part in approvals: asked for one, holds or held a place in one, or has one waiting (also through a delegation or a group role). Lets the client show the approval pages to an approver who holds no approval permission. Provided by Approval. */
interface ApprovalParticipation
{
    public function takesPart(Actor $actor): bool;
}
