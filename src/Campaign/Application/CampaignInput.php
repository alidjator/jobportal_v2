<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\CampaignType;

/** The fields of a campaign a person can set; the dates are Jakarta calendar days (`Y-m-d`). */
final readonly class CampaignInput
{
    public function __construct(
        public string $name,
        public CampaignType $type,
        public ?string $startsOn,
        public ?string $endsOn,
        public bool $active,
    ) {}
}
