<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Scorecard;

/** A scorecard with the name of its member (null when the account no longer has a name). */
final readonly class ScorecardView
{
    public function __construct(public Scorecard $scorecard, public ?string $memberName) {}
}
