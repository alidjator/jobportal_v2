<?php

namespace Src\Shared\Application\Company;

/** An active work location of a tenant as other contexts need it: its name and its address as one line of text. */
final readonly class CompanyLocationPlace
{
    public function __construct(
        public int $id,
        public string $name,
        public string $addressText,
    ) {}
}
