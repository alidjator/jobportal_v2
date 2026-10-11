<?php

namespace Src\Assessment\Domain;

/**
 * A timed part of a test. `drawCount` null means every question is asked, otherwise that many are drawn at random from the section.
 * `settings` holds the format's own numbers (the Kraepelin columns).
 */
final readonly class Section
{
    /**
     * @param  array<string, int>  $settings
     */
    public function __construct(
        public ?int $id,
        public int $testId,
        public int $position,
        public string $title,
        public ?string $instructions,
        public SectionFormat $format,
        public int $durationSeconds,
        public bool $allowSkip,
        public bool $allowBack,
        public bool $allowJump,
        public ?int $drawCount,
        public array $settings,
    ) {}
}
