<?php

namespace Src\Shared\Application\Organization;

/** An active cost centre of a legal entity as a form offers it. */
final readonly class CostCentreOption
{
    public function __construct(public int $id, public string $code, public string $name) {}
}
