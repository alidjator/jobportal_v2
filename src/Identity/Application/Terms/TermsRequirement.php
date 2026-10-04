<?php

namespace Src\Identity\Application\Terms;

/** One legal document in force and whether the user has accepted exactly that version. */
final readonly class TermsRequirement
{
    public function __construct(
        public string $type,
        public int $version,
        public bool $accepted,
    ) {}
}
