<?php

namespace Src\ReferenceData\Application\Masters;

use Src\Shared\Domain\TranslatedText;

/** One row of a master table. `extras` holds the master's extra columns by name. */
final readonly class MasterItem
{
    /** @param  array<string, int|string|null>  $extras */
    public function __construct(
        public int $id,
        public string $slug,
        public TranslatedText $name,
        public int $sortOrder,
        public bool $isActive,
        public array $extras,
    ) {}
}
