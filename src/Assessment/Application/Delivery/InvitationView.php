<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\TestKind;

/** An invitation as the team sees it: the test it sends and the members who score its long-text answers. */
final readonly class InvitationView
{
    /** @param  list<array{id: int, name: string}>  $assessors */
    public function __construct(public Invitation $invitation, public string $testTitle, public TestKind $testKind, public array $assessors) {}
}
