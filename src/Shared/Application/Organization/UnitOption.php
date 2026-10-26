<?php

namespace Src\Shared\Application\Organization;

/** An active organisation unit as it is on a day, as a form offers it: its name from the version in force and its default cost centre. */
final readonly class UnitOption
{
    public function __construct(public int $id, public string $code, public string $name, public ?int $costCentreId) {}
}
