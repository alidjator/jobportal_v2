<?php

namespace Src\Shared\Application\JobInvitation;

use DateTimeImmutable;

/** The latest job invitation a company sent to a person: the job title (null when the job no longer exists), the status and when it was sent. */
final readonly class LatestInvitation
{
    public function __construct(public ?string $jobTitle, public string $status, public DateTimeImmutable $sentAt) {}
}
