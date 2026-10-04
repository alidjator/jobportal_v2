<?php

namespace Src\Shared\Application\Email;

/** A value a template may use as `{{name}}`: what it is, a sample for previews, and whether the body must contain it. */
final readonly class EmailVariable
{
    public function __construct(
        public string $name,
        public string $description,
        public string $sample,
        public bool $required = false,
    ) {}
}
