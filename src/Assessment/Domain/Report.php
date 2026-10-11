<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The report of one psychological attempt. It starts as a draft built from the report template, the scores and the norms; a psychologist of the provider
 * may edit the text, writes the explanation for the candidate and signs. Signing gives it a number (`CODE/YEAR/SEQUENCE`), stamps the time and keeps a copy of
 * the psychologist's name and licence numbers; a signed report never changes again.
 */
final readonly class Report
{
    public function __construct(
        public ?int $id,
        public int $attemptId,
        public int $providerId,
        public ReportStatus $status,
        public string $body,
        public ?string $explanation,
        public ?string $reviewNotes,
        public ?DateTimeImmutable $editedAt,
        public ?string $number,
        public ?int $year,
        public ?int $sequence,
        public ?int $signedByPsychologistId,
        public ?string $signerName,
        public ?string $signerStrNumber,
        public ?string $signerSilpNumber,
        public ?DateTimeImmutable $signedAt,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function draft(int $attemptId, int $providerId, string $body): self
    {
        return new self(null, $attemptId, $providerId, ReportStatus::PendingReview, $body, null, null, null, null, null, null, null, null, null, null, null);
    }

    public function isSigned(): bool
    {
        return $this->status === ReportStatus::Signed;
    }

    /**
     * @throws StateConflict `report-signed`
     */
    public function edit(string $body, ?string $explanation, ?string $reviewNotes, DateTimeImmutable $now): self
    {
        $this->assertOpen();

        return new self(
            $this->id, $this->attemptId, $this->providerId, $this->status, $body, $explanation, $reviewNotes, $now,
            null, null, null, null, null, null, null, null, $this->createdAt,
        );
    }

    /**
     * The draft of the same attempt built again from the scores: only when nobody edited it yet. A signed report is never replaced.
     */
    public function redrafted(string $body): self
    {
        if ($this->isSigned() || $this->editedAt !== null) {
            return $this;
        }

        return new self(
            $this->id, $this->attemptId, $this->providerId, $this->status, $body, $this->explanation, $this->reviewNotes, null,
            null, null, null, null, null, null, null, null, $this->createdAt,
        );
    }

    /**
     * @throws StateConflict `report-signed`
     */
    public function sign(string $body, string $number, int $year, int $sequence, Psychologist $psychologist, string $signerName, DateTimeImmutable $now): self
    {
        $this->assertOpen();

        return new self(
            $this->id, $this->attemptId, $this->providerId, ReportStatus::Signed, $body, $this->explanation, $this->reviewNotes, $this->editedAt,
            $number, $year, $sequence, $psychologist->id, $signerName, $psychologist->strNumber(), $psychologist->silpNumber(), $now, $this->createdAt,
        );
    }

    private function assertOpen(): void
    {
        if ($this->isSigned()) {
            throw new StateConflict('This report is signed and cannot be changed.', 'report-signed');
        }
    }
}
