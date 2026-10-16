<?php

namespace Src\Job\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Job\RequisitionJobCreator;

/** The port of the Workforce context for opening a job: the ordinary job creation, with the requisition named. */
final readonly class JobFromRequisition implements RequisitionJobCreator
{
    public function __construct(private CreateJob $create) {}

    public function create(Actor $actor, int $requisitionId, string $title, int $vacancies): int
    {
        return $this->create->execute($actor, $title, ['vacancies' => $vacancies], $requisitionId);
    }
}
