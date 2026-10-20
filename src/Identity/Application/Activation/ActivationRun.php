<?php

namespace Src\Identity\Application\Activation;

use Src\Identity\Domain\UserType;

/**
 * One run of the send command. `$emails` limits it to these (lower case) addresses: the staff test, which sends `manual` emails and skips the date guard and the stop rule.
 */
final readonly class ActivationRun
{
    /** @param  list<string>|null  $emails */
    public function __construct(
        public UserType $audience,
        public bool $write,
        public int $limit,
        public bool $reminders = false,
        public bool $pilot = false,
        public ?array $emails = null,
    ) {}
}
