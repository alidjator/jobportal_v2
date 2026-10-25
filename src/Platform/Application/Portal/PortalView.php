<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Company\PublicCompanyCard;

/**
 * The portal as a visitor of the career site sees it. In the marketplace there is no brand and no companies (every company is shown). On a company site `companies` are the
 * ones that can be shown now, in the order they were named; `name` is the brand name or, without one, the name of the company or group. `preview` marks a view made from a draft.
 */
final readonly class PortalView
{
    /** @param  list<PublicCompanyCard>  $companies */
    public function __construct(
        public string $mode,
        public ?string $scope,
        public ?string $name,
        public ?string $tagline,
        public ?string $welcome,
        public int $preset,
        public array $companies,
        public bool $preview,
    ) {}
}
