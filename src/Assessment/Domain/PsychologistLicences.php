<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/**
 * An active psychologist of an active provider with the end of the STR and the SILP and what the expiry notices already told. `notified` is `{kind: {valid_until: 'Y-m-d', sent: [60, 30, ...]}}`;
 * an entry about another end date than the licence has now is stale (the licence was renewed) and counts as nothing sent.
 */
final readonly class PsychologistLicences
{
    /** @param  array<string, mixed>  $notified */
    public function __construct(
        public int $psychologistId,
        public int $userId,
        public int $providerId,
        public string $providerName,
        public DateTimeImmutable $strValidUntil,
        public DateTimeImmutable $silpValidUntil,
        public array $notified,
    ) {}
}
