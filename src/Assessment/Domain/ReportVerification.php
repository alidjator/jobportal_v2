<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/**
 * What the public check of a report number says (`docs/design/phase-8-provider-tools.md` section 7): who issued it and when, which revision it is, whether a later revision replaced it and the SHA-256 of
 * the stored PDF. Nothing about the candidate, the company, the job, the scales or the recommendation. `documentErased` is true once the candidate's account was anonymised: the number, the signer and the date
 * stay, the PDF is gone.
 */
final readonly class ReportVerification
{
    public function __construct(
        public string $providerName,
        public string $providerCode,
        public string $psychologistName,
        public DateTimeImmutable $signedAt,
        public int $revision,
        public bool $superseded,
        public ?string $sha256,
        public bool $documentErased,
    ) {}
}
