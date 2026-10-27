<?php

namespace Src\Identity\Interface\Http\Payloads;

use Src\Identity\Application\Terms\TermsRequirement;

/** The state of every required legal document for the caller, as the answer carries it. */
final readonly class TermsStatusPayload
{
    /** @param  list<TermsRequirement>  $requirements */
    public function __construct(public array $requirements) {}
}
