<?php

namespace Src\Shared\Application\Accounts;

/** The id and name of a platform admin account. */
final readonly class AdminSummary
{
    public function __construct(public int $id, public string $name) {}
}
