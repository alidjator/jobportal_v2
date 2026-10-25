<?php

namespace Src\Platform\Application\Portal;

use DateTimeImmutable;

/** What the portal screen of the platform admin shows: the live identity (the marketplace when none), the draft waiting, and the companies the live identity names but the site skips. */
final readonly class PortalAdminPage
{
    /** @param  list<PortalSkippedCompany>  $skipped */
    public function __construct(
        public PortalIdentity $live,
        public bool $liveIsDefault,
        public ?PortalIdentity $draft,
        public ?DateTimeImmutable $draftSavedAt,
        public array $skipped,
    ) {}

    public function draftDiffers(): bool
    {
        return $this->draft !== null && ! $this->draft->equals($this->live);
    }
}
