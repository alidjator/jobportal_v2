<?php

namespace Src\Identity\Application\Activation;

/**
 * What a run found and did. `eligible` is every imported account of the audience that has not signed in; the other counts split it. `unverifiedHeldBack` are the accounts whose
 * address was never verified while verified ones are still to be sent (they come last). `blockedBy` lists the guards that would stop a real run (a dry run reports them, a real run throws).
 */
final readonly class ActivationReport
{
    /** @param  list<string>  $blockedBy */
    public function __construct(
        public string $batch,
        public int $eligible,
        public int $suppressed,
        public int $deactivated,
        public int $alreadySent,
        public int $notDue,
        public int $unverifiedHeldBack,
        public int $sendable,
        public int $sent,
        public ?int $signedIn,
        public array $blockedBy,
    ) {}
}
