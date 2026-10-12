<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\Test;

/** An invitation as the candidate sees it; `sections` and `hasVideo` (the test has video questions, so starting it needs a separate recording consent) are set when one invitation is read, never in a list. */
final readonly class MyInvitationView
{
    /** @param  list<Section>|null  $sections */
    public function __construct(
        public Invitation $invitation,
        public Test $test,
        public string $jobTitle,
        public string $companyName,
        public ?array $sections,
        public bool $hasVideo = false,
    ) {}
}
