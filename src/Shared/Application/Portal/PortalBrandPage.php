<?php

namespace Src\Shared\Application\Portal;

use DateTimeImmutable;

/**
 * What the brand screen of a company or group shows: the brand that is live (the default when none was published), the draft still waiting (null when there is none), and
 * the name the site uses while the brand has no name of its own.
 */
final readonly class PortalBrandPage
{
    public function __construct(
        public PortalBrandValues $live,
        public bool $liveIsDefault,
        public ?PortalBrandValues $draft,
        public ?DateTimeImmutable $draftSavedAt,
        public string $defaultName,
    ) {}

    /** True when the draft would change what is live. */
    public function draftDiffers(): bool
    {
        return $this->draft !== null && ! $this->draft->equals($this->live);
    }
}
