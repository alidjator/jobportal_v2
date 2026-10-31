<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\Test;

/** The practice questions of the test of an invitation, by section. */
final readonly class PracticeView
{
    /** @param  list<PracticeSection>  $sections */
    public function __construct(public Invitation $invitation, public Test $test, public array $sections) {}
}
