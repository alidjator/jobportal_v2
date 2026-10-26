<?php

namespace Src\Shared\Application\Organization;

/** An active grade of a group as a form offers it: id, code, name and rank (higher is more senior). */
final readonly class GradeOption
{
    public function __construct(public int $id, public string $code, public string $name, public int $rank) {}
}
