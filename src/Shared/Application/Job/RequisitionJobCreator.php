<?php

namespace Src\Shared\Application\Job;

use Src\Shared\Application\Actor;

/** Opens a draft job for a requisition on behalf of a company member, with the checks of any new job. Provided by Job. */
interface RequisitionJobCreator
{
    /** @return int the id of the new draft job */
    public function create(Actor $actor, int $requisitionId, string $title, int $vacancies): int;
}
