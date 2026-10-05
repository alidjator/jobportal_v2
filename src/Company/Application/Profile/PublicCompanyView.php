<?php

namespace Src\Company\Application\Profile;

use DateTimeImmutable;
use Src\Shared\Application\References\LocationNames;

/** What anyone may see of a company. It holds no contact data (email, phone), and the names are in the language of the request. */
final readonly class PublicCompanyView
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $bio,
        public ?string $vision,
        public ?string $website,
        public ?DateTimeImmutable $establishedOn,
        public ?string $industry,
        public ?string $organizationType,
        public ?string $teamSize,
        public ?string $addressLine,
        public LocationNames $location,
        public ?DateTimeImmutable $verifiedAt,
        public ?string $logoVersion,
        public ?string $bannerVersion,
    ) {}
}
