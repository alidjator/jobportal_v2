<?php

namespace Src\TalentPool\Application;

/** A category with the number of candidates in it. */
final readonly class CategoryView
{
    public function __construct(public int $id, public string $name, public int $entries) {}
}
