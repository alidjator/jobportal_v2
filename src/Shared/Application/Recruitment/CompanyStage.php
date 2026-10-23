<?php

namespace Src\Shared\Application\Recruitment;

/** A stage of a company's pipeline as other contexts see it: its id, name and type (`applied`, `screening`, `assessment`, `interview`, `offer`, `hired`, `rejected`, `withdrawn`). */
final readonly class CompanyStage
{
    public function __construct(public int $id, public string $name, public string $type) {}
}
