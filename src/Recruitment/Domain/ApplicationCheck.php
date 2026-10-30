<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * One check on one application (`docs/design/phase-8-pre-employment-checks.md`): requested, waiting for the candidate's consent when the type needs it, scheduled, then completed with an outcome,
 * or waived or cancelled from any open state. Immutable: a change returns a new instance. The outcome, its note and its file are for the team only; a medical check keeps a category only.
 */
final readonly class ApplicationCheck
{
    public const int PLACE_MAX = 200;

    public const int VENDOR_MAX = 150;

    public const int NOTE_MAX = 1000;

    public const int REASON_MAX = 500;

    public function __construct(
        public int $applicationId,
        public int $checkTypeId,
        public CheckStatus $status,
        public bool $consentRequired,
        public ?DateTimeImmutable $scheduledAt,
        public ?string $place,
        public ?string $vendor,
        public ?string $noteToCandidate,
        public ?DateTimeImmutable $consentedAt,
        public ?DateTimeImmutable $consentRefusedAt,
        public ?string $refusalReason,
        public ?CheckOutcome $outcome,
        public ?string $resultNote,
        public ?string $resultFilePath,
        public ?string $resultFileName,
        public ?int $resultRecordedBy,
        public ?DateTimeImmutable $resultRecordedAt,
        public int $requestedBy,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {}

    /** @throws BusinessRuleViolation `invalid-check` */
    public static function request(
        int $applicationId, int $checkTypeId, bool $consentRequired, ?DateTimeImmutable $scheduledAt, ?string $place, ?string $vendor, ?string $noteToCandidate, int $requestedBy, DateTimeImmutable $now,
    ): self {
        $check = new self(
            $applicationId, $checkTypeId, CheckStatus::Requested, $consentRequired, $scheduledAt, self::text($place, self::PLACE_MAX, 'place'), self::text($vendor, self::VENDOR_MAX, 'vendor'),
            self::text($noteToCandidate, self::NOTE_MAX, 'note_to_candidate'), null, null, null, null, null, null, null, null, null, $requestedBy, $now, $now,
        );

        return $check->settled();
    }

    /**
     * The team changes the schedule, the place, the vendor or the note to the candidate while the check is open.
     *
     * @throws StateConflict `check-closed`
     * @throws BusinessRuleViolation `invalid-check`
     */
    public function changed(?DateTimeImmutable $scheduledAt, ?string $place, ?string $vendor, ?string $noteToCandidate, DateTimeImmutable $now): self
    {
        $this->assertOpen();

        return (new self(
            $this->applicationId, $this->checkTypeId, $this->status, $this->consentRequired, $scheduledAt, self::text($place, self::PLACE_MAX, 'place'), self::text($vendor, self::VENDOR_MAX, 'vendor'),
            self::text($noteToCandidate, self::NOTE_MAX, 'note_to_candidate'), $this->consentedAt, $this->consentRefusedAt, $this->refusalReason, $this->outcome, $this->resultNote,
            $this->resultFilePath, $this->resultFileName, $this->resultRecordedBy, $this->resultRecordedAt, $this->requestedBy, $this->createdAt, $now, $this->id,
        ))->settled();
    }

    /**
     * The candidate agrees. A refusal given before is withdrawn.
     *
     * @throws StateConflict `check-closed`
     */
    public function consented(DateTimeImmutable $now): self
    {
        $this->assertOpen();

        return $this->with(['consentedAt' => $now, 'consentRefusedAt' => null, 'refusalReason' => null, 'updatedAt' => $now])->settled();
    }

    /**
     * The candidate refuses. The check stays requested and the team sees the refusal; nothing moves by itself.
     *
     * @throws StateConflict `check-closed`
     * @throws BusinessRuleViolation `invalid-check`
     */
    public function refused(?string $reason, DateTimeImmutable $now): self
    {
        $this->assertOpen();

        return $this->with(['consentedAt' => null, 'consentRefusedAt' => $now, 'refusalReason' => self::text($reason, self::REASON_MAX, 'reason'), 'updatedAt' => $now])->settled();
    }

    /** @throws StateConflict `check-closed` */
    public function waived(DateTimeImmutable $now): self
    {
        $this->assertOpen();

        return $this->with(['status' => CheckStatus::Waived, 'updatedAt' => $now]);
    }

    /** @throws StateConflict `check-closed` */
    public function cancelled(DateTimeImmutable $now): self
    {
        $this->assertOpen();

        return $this->with(['status' => CheckStatus::Cancelled, 'updatedAt' => $now]);
    }

    /**
     * Records the result and completes the check. A result waits for the candidate's consent when the check needs it.
     *
     * @throws StateConflict `check-closed` or `check-consent-pending`
     * @throws BusinessRuleViolation `invalid-check`
     */
    public function completed(CheckOutcome $outcome, ?string $note, ?string $filePath, ?string $fileName, int $recordedBy, DateTimeImmutable $now): self
    {
        $this->assertOpen();

        if ($this->consentRequired && $this->consentedAt === null) {
            throw new StateConflict('The candidate has not agreed to this check yet.', 'check-consent-pending');
        }

        return $this->with([
            'status' => CheckStatus::Completed, 'outcome' => $outcome, 'resultNote' => self::text($note, self::NOTE_MAX, 'note'), 'resultFilePath' => $filePath, 'resultFileName' => $fileName,
            'resultRecordedBy' => $recordedBy, 'resultRecordedAt' => $now, 'updatedAt' => $now,
        ]);
    }

    /** The outcome, the note and the file go (the retention ended, or the application was erased); the status and the fact that it was done stay. */
    public function withoutResult(DateTimeImmutable $now): self
    {
        return $this->with(['outcome' => null, 'resultNote' => null, 'resultFilePath' => null, 'resultFileName' => null, 'updatedAt' => $now]);
    }

    /** Whether the candidate still has to answer. */
    public function awaitsConsent(): bool
    {
        return $this->status->isOpen() && $this->consentRequired && $this->consentedAt === null;
    }

    /** The open status that follows from what is known: scheduled once a time is set and the consent is in; consented once the consent is in; else requested. */
    private function settled(): self
    {
        if (! $this->status->isOpen()) {
            return $this;
        }

        $consentIn = ! $this->consentRequired || $this->consentedAt !== null;
        $status = match (true) {
            $consentIn && $this->scheduledAt !== null => CheckStatus::Scheduled,
            $this->consentedAt !== null => CheckStatus::Consented,
            default => CheckStatus::Requested,
        };

        return $status === $this->status ? $this : $this->with(['status' => $status]);
    }

    /** @throws StateConflict `check-closed` */
    private function assertOpen(): void
    {
        if (! $this->status->isOpen()) {
            throw new StateConflict('This check is closed.', 'check-closed');
        }
    }

    /** @throws BusinessRuleViolation `invalid-check` */
    private static function text(?string $text, int $max, string $field): ?string
    {
        $text = $text === null ? null : trim($text);

        if ($text === null || $text === '') {
            return null;
        }

        if (mb_strlen($text) > $max) {
            throw new BusinessRuleViolation('The text of :field has at most :max characters.', 'invalid-check', extensions: ['errors' => [$field => ['Too long.']]], parameters: ['field' => $field, 'max' => $max]);
        }

        return $text;
    }

    /** @param  array<string, mixed>  $changes */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
