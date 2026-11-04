<?php

namespace Src\Assessment\Application\Library\Figural;

/**
 * One item of the figural reasoning test: the frames of the stem (none for an odd-one-out item), the five option frames A to E, the position of the right one and the rule the item tests (for the author, never shown to the
 * candidate).
 *
 * @phpstan-type Element list<mixed>
 * @phpstan-type Frame list<Element>
 */
final readonly class FiguralItem
{
    /**
     * @param  list<Frame>  $stem
     * @param  list<Frame>  $options  five, A to E
     * @param  int  $key  0-based position of the right option
     */
    public function __construct(
        public array $stem,
        public array $options,
        public int $key,
        public string $rule,
        public bool $isSample = false,
    ) {}
}
