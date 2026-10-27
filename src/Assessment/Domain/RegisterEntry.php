<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/**
 * One line of the provider's report register: a signed or automatically issued report, with who answers for it and for which battery and company. The register keeps no candidate: it answers the duty to keep a record of the reports,
 * not a question about a person. `erased` is true once the candidate's account was anonymised (the text is gone, the number, the signer and the time stay).
 */
final readonly class RegisterEntry
{
    public function __construct(
        public int $reportId,
        public string $number,
        public int $revision,
        public DateTimeImmutable $signedAt,
        public ?int $psychologistId,
        public ?string $signerName,
        public int $testId,
        public string $testTitle,
        public ?int $companyId,
        public bool $autoIssued,
        public ?DateTimeImmutable $reviewedAt,
        public ?Recommendation $recommendation,
        public bool $erased,
    ) {}

    /** A report signed by hand is reviewed by the signature itself; an automatic one waits for a psychologist. */
    public function reviewed(): bool
    {
        return ! $this->autoIssued || $this->reviewedAt !== null;
    }
}
