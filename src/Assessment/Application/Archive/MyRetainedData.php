<?php

namespace Src\Assessment\Application\Archive;

/** What the erasure of the person's account would keep: how many psychological attempts, for how many years; or nothing while the retention switch is off. */
final readonly class MyRetainedData
{
    public function __construct(public ?int $years, public int $examinations) {}
}
