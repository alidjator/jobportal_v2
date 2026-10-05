<?php

namespace Src\ReferenceData\Application\Masters;

use InvalidArgumentException;
use Src\Shared\Application\References\ReferenceValues;

final readonly class MasterReferenceValues implements ReferenceValues
{
    public function __construct(private MasterRepository $masters) {}

    public function isActive(string $master, int $id): bool
    {
        $definition = MasterCatalog::find($master)
            ?? throw new InvalidArgumentException("Unknown master {$master}.");

        $item = $this->masters->find($definition, $id);

        return $item !== null && $item->isActive;
    }
}
