<?php

namespace Src\Shared\Application\Job;

use DateTimeImmutable;

/** A job an account is assigned to, as the company's team page lists it. `status` is one of the seven job statuses. */
final readonly class AssignedJob
{
    public function __construct(public int $id, public string $title, public string $status, public ?DateTimeImmutable $deadline) {}
}
