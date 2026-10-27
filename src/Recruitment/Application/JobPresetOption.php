<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningPreset;

/** A preset as the menu of a job offers it: `suggested` when its level hint is the level of the job. */
final readonly class JobPresetOption
{
    public function __construct(public ScreeningPreset $preset, public bool $suggested) {}
}
