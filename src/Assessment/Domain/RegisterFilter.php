<?php

namespace Src\Assessment\Domain;

/**
 * What narrows the report register (`docs/design/phase-8-provider-tools.md` section 8): the Jakarta days the reports were signed between (both included, `Y-m-d`), the psychologist who signed or answers for them,
 * and the battery. Every field is optional.
 */
final readonly class RegisterFilter
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public ?int $psychologistId = null,
        public ?int $testId = null,
    ) {}
}
