<?php

namespace Src\Assessment\Application\Library\Figural;

/** One subtest of the figural reasoning test: its own instructions (with a worked example in words), time, prompt and layout, and its items from easy to hard. */
final readonly class FiguralSection
{
    /** @param  list<FiguralItem>  $items */
    public function __construct(
        public string $title,
        public string $instructions,
        public int $seconds,
        public string $prompt,
        public FiguralLayout $layout,
        public ?string $stemAlt,
        public array $items,
    ) {}
}
