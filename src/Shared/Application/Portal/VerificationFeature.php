<?php

namespace Src\Shared\Application\Portal;

/**
 * Whether the verification of companies exists on this installation: the verification documents, the "verified" badge, the requests to change the name of a verified company and the two rules that follow the
 * verification (the approval of the jobs of an unverified company and the closed contacts of its applicants). It exists on the marketplace only; a company portal (`company` mode) has none of it
 * (`docs/design/career-modes.md`, decided 2026-10-28). Provided by the Platform context, which holds the portal identity.
 */
interface VerificationFeature
{
    public function enabled(): bool;
}
