<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The report of one psychological attempt. It starts as a draft built from the report template, the scores and the norms; a psychologist of the provider
 * may edit the text, writes the explanation for the candidate and signs. Signing gives it a number (`CODE/YEAR/SEQUENCE`), stamps the time and keeps a copy of
 * the psychologist's name and licence numbers; a signed report never changes again. The one exception is a report issued automatically (`autoIssued`, signed at once
 * under the responsible psychologist of its battery): a psychologist of the provider marks it reviewed, or issues a revision that keeps the number and adds `-R1`,
 * `-R2` (the version it replaces is kept by the repository).
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
        public bool $autoIssued = false,
        public ?DateTimeImmutable $reviewedAt = null,
        public ?int $reviewedByPsychologistId = null,
        public int $revision = 0,
        public ?int $claimedByPsychologistId = null,
        public ?DateTimeImmutable $claimedAt = null,
        public ?DateTimeImmutable $claimTouchedAt = null,
    ) {}

    public static function draft(int $attemptId, int $providerId, string $body): self
    {
        return new self(null, $attemptId, $providerId, ReportStatus::PendingReview, $body, null, null, null, null, null, null, null, null, null, null, null);
    }

    public function isSigned(): bool
    {
        return $this->status === ReportStatus::Signed;
    }

    /** A claim lapses after this long without a write by its holder (`docs/design/phase-8-provider-tools.md` section 3). */
    public const string CLAIM_LAPSE = '-24 hours';

    /** Whether a psychologist holds the report and has written to it within the last 24 hours. */
    public function hasLiveClaim(DateTimeImmutable $now): bool
    {
        return $this->claimedByPsychologistId !== null && $this->claimTouchedAt !== null && $this->claimTouchedAt > $now->modify(self::CLAIM_LAPSE);
    }

    public function isClaimedBy(int $psychologistId, DateTimeImmutable $now): bool
    {
        return $this->claimedByPsychologistId === $psychologistId && $this->hasLiveClaim($now);
    }

    /** The psychologist takes the report (a live claim of someone else is checked by the caller); the time of an earlier own live claim stays. */
    public function claimedBy(Psychologist $psychologist, DateTimeImmutable $now): self
    {
        $own = $this->isClaimedBy((int) $psychologist->id, $now);

        return $this->with(['claimedByPsychologistId' => $psychologist->id, 'claimedAt' => $own ? $this->claimedAt : $now, 'claimTouchedAt' => $now]);
    }

    /** The holder wrote to the report: the claim lives another 24 hours. */
    public function claimTouched(DateTimeImmutable $now): self
    {
        return $this->claimedByPsychologistId === null ? $this : $this->with(['claimTouchedAt' => $now]);
    }

    public function claimReleased(): self
    {
        return $this->with(['claimedByPsychologistId' => null, 'claimedAt' => null, 'claimTouchedAt' => null]);
    }

    /** The number without the revision suffix. */
    public function baseNumber(): ?string
    {
        return $this->number === null ? null : (string) preg_replace('/-R\d+$/', '', $this->number);
    }

    /**
     * @throws StateConflict `report-signed`
     */
    public function edit(string $body, ?string $explanation, ?string $reviewNotes, DateTimeImmutable $now): self
    {
        $this->assertOpen();

        return $this->with(['body' => $body, 'explanation' => $explanation, 'reviewNotes' => $reviewNotes, 'editedAt' => $now]);
    }

    /**
     * The draft of the same attempt built again from the scores: only when nobody edited it yet. A signed report is never replaced.
     */
    public function redrafted(string $body): self
    {
        if ($this->isSigned() || $this->editedAt !== null) {
            return $this;
        }

        return $this->with(['body' => $body]);
    }

    /**
     * @throws StateConflict `report-signed`
     */
    public function sign(string $body, string $number, int $year, int $sequence, Psychologist $psychologist, string $signerName, DateTimeImmutable $now): self
    {
        $this->assertOpen();

        return $this->with([
            'status' => ReportStatus::Signed, 'body' => $body, 'number' => $number, 'year' => $year, 'sequence' => $sequence, 'signedByPsychologistId' => $psychologist->id,
            'signerName' => $signerName, 'signerStrNumber' => $psychologist->strNumber(), 'signerSilpNumber' => $psychologist->silpNumber(), 'signedAt' => $now,
        ]);
    }

    /**
     * The report is issued at once, without a psychologist's hands: signed under the responsible psychologist with the explanation made from the battery's template.
     *
     * @throws StateConflict `report-signed`
     */
    public function issueAutomatically(string $body, string $explanation, string $number, int $year, int $sequence, Psychologist $responsible, string $signerName, DateTimeImmutable $now): self
    {
        $this->assertOpen();

        return $this->with([
            'status' => ReportStatus::Signed, 'body' => $body, 'explanation' => $explanation, 'number' => $number, 'year' => $year, 'sequence' => $sequence,
            'signedByPsychologistId' => $responsible->id, 'signerName' => $signerName, 'signerStrNumber' => $responsible->strNumber(), 'signerSilpNumber' => $responsible->silpNumber(),
            'signedAt' => $now, 'autoIssued' => true,
        ]);
    }

    /**
     * A psychologist of the provider has read the report that was issued automatically. Marking it again changes nothing.
     *
     * @throws StateConflict `report-not-auto-issued`
     */
    public function markedReviewed(Psychologist $psychologist, DateTimeImmutable $now): self
    {
        $this->assertAutoIssued();

        return $this->reviewedAt !== null ? $this : $this->with(['reviewedAt' => $now, 'reviewedByPsychologistId' => $psychologist->id]);
    }

    /**
     * A psychologist replaces the text and the explanation of a report that was issued automatically. The number stays and gets the suffix `-R<n>`; the reviser signs.
     *
     * @throws StateConflict `report-not-auto-issued`
     */
    public function revised(string $body, string $explanation, Psychologist $reviser, string $signerName, DateTimeImmutable $now): self
    {
        $this->assertAutoIssued();

        $revision = $this->revision + 1;

        return $this->with([
            'body' => $body, 'explanation' => $explanation, 'number' => $this->baseNumber().'-R'.$revision, 'revision' => $revision, 'signedByPsychologistId' => $reviser->id,
            'signerName' => $signerName, 'signerStrNumber' => $reviser->strNumber(), 'signerSilpNumber' => $reviser->silpNumber(), 'signedAt' => $now,
            'reviewedAt' => $now, 'reviewedByPsychologistId' => $reviser->id,
        ]);
    }

    /** The same report with another text (the placeholders of the signature filled in). */
    public function withBody(string $body): self
    {
        return $this->with(['body' => $body]);
    }

    private function assertOpen(): void
    {
        if ($this->isSigned()) {
            throw new StateConflict('This report is signed and cannot be changed.', 'report-signed');
        }
    }

    private function assertAutoIssued(): void
    {
        if (! $this->isSigned() || ! $this->autoIssued) {
            throw new StateConflict('Only a report that was issued automatically can be marked reviewed or revised.', 'report-not-auto-issued');
        }
    }

    /** @param  array<string, mixed>  $changes  the promoted properties to change, by name */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
