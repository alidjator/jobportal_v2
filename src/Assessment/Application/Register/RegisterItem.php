<?php

namespace Src\Assessment\Application\Register;

use Src\Assessment\Domain\RegisterEntry;

/** A line of the report register with the name of the company the test was sent for. */
final readonly class RegisterItem
{
    public function __construct(
        public RegisterEntry $entry,
        public ?string $companyName,
    ) {}
}
