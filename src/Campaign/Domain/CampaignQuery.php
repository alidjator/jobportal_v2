<?php

namespace Src\Campaign\Domain;

/** Which campaigns to list: of one company (null: every campaign), by a text in the name or code, by type, and by what they are on `$today` (`Y-m-d`). */
final readonly class CampaignQuery
{
    public function __construct(
        public ?int $companyId,
        public ?string $search,
        public ?CampaignType $type,
        public ?CampaignStatus $status,
        public string $today,
    ) {}
}
