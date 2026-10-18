<?php

namespace Src\Shared\Application\Recruitment;

use DateTimeImmutable;

/** An application of a person to the company, as the pool shows it: the job title and the status (the stage type) for the whole team, the id only when the member may see the application. */
final readonly class PoolApplicationRef
{
    public function __construct(public ?int $id, public ?string $jobTitle, public string $status, public DateTimeImmutable $appliedAt) {}
}
