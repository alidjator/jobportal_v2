<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\SectionFormat;

/** What an author submits for a section. */
final readonly class SectionDraft
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $title,
        public ?string $instructions,
        public SectionFormat $format,
        public ?int $durationSeconds,
        public bool $allowSkip,
        public bool $allowBack,
        public bool $allowJump,
        public ?int $drawCount,
        public array $settings,
    ) {}
}
