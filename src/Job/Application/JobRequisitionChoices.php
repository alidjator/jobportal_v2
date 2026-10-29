<?php

namespace Src\Job\Application;

use Src\Shared\Application\Workforce\OpenRequisition;

/** What the form of a new job asks of the requisitions: whether the tenant must open its jobs from one, and the ones still open for the tenant. */
final readonly class JobRequisitionChoices
{
    /** @param  list<OpenRequisition>  $requisitions */
    public function __construct(public bool $required, public array $requisitions) {}
}
