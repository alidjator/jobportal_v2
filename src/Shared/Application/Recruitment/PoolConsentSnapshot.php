<?php

namespace Src\Shared\Application\Recruitment;

use DateTimeImmutable;

/**
 * The profile of an applicant as it stood in their latest consenting application to a company: name, headline, city, latest position and up to five skills.
 * Never the live profile and never contact data. `profile` is the whole snapshot without the contact data (email, phone, WhatsApp, street address and links), for the
 * entry page.
 */
final readonly class PoolConsentSnapshot
{
    /**
     * @param  array{title: string, employer: string}|null  $latestPosition
     * @param  list<string>  $skills
     * @param  array<string, mixed>  $profile
     */
    public function __construct(
        public int $applicationId,
        public DateTimeImmutable $appliedAt,
        public ?string $name,
        public ?string $headline,
        public ?string $city,
        public ?array $latestPosition,
        public array $skills,
        public array $profile = [],
    ) {}
}
