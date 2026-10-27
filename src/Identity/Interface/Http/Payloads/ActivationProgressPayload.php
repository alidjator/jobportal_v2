<?php

namespace Src\Identity\Interface\Http\Payloads;

/** The activation report of one audience, as the answer carries it. */
final readonly class ActivationProgressPayload
{
    /** @param  array<string, mixed>  $report */
    public function __construct(public array $report) {}
}
