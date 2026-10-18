<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/** A change to the hire details of a hired application: only the parts the request gave (`...Given`) change, the others stay as they are. */
final readonly class HireChange
{
    public function __construct(
        public bool $joinDateGiven,
        public ?DateTimeImmutable $joinDate,
        public bool $noteGiven,
        public ?string $note,
    ) {}
}
