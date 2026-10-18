<?php

namespace Src\Shared\Application\Company;

/** What a company puts on top of a letter: its name, its address on one line and its logo as a `data:` URI (null when it has none). Owned by Company. */
final readonly class CompanyLetterhead
{
    public function __construct(public string $name, public ?string $address, public ?string $logoDataUri) {}
}
