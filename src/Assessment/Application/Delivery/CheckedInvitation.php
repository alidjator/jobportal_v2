<?php

namespace Src\Assessment\Application\Delivery;

use DateTimeImmutable;
use Src\Assessment\Domain\InvitationMode;
use Src\Assessment\Domain\Test;

/** A request to send a test that passed every rule: the test, the resolved start time, the assessors without duplicates where the test is taken and the note for the candidate. */
final readonly class CheckedInvitation
{
    /** @param  list<int>  $assessorIds */
    public function __construct(public Test $test, public DateTimeImmutable $startsAt, public DateTimeImmutable $dueAt, public array $assessorIds, public InvitationMode $mode = InvitationMode::Online, public ?string $location = null, public ?string $note = null) {}
}
