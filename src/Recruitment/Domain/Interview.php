<?php

namespace Src\Recruitment\Domain;

use DateInterval;
use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * An interview of one application. The team schedules it (`pending`), the job seeker confirms it, declines it or proposes another time (at most
 * {@see self::PROPOSALS_MAX} times, also to move a confirmed interview up to a cut-off before its start), and the team accepts or refuses a proposal, cancels it,
 * marks it completed or, after its start, records a no-show. An application has at most one active interview.
 * A video or voice interview may be marked `recorded`: the job seeker then consents or refuses when they answer the schedule, and may withdraw a given consent.
 */
final readonly class Interview
{
    public const int PROPOSALS_MAX = 2;

    public const int DURATION_MIN = 5;

    public const int DURATION_MAX = 480;

    public const int LOCATION_MAX = 255;

    public const int URL_MAX = 500;

    public const int NOTE_MAX = 1000;

    public const int PROPOSAL_REASON_MAX = 500;

    public function __construct(
        public int $applicationId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public InterviewFormat $format,
        public ?string $location,
        public ?string $meetingUrl,
        public int $interviewerId,
        public int $createdBy,
        public InterviewStatus $status,
        public int $proposalsUsed,
        public ?DateTimeImmutable $proposedStartsAt,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $remindedAt = null,
        public ?int $kitId = null,
        public ?string $kitName = null,
        public bool $recorded = false,
        public ?RecordingConsent $recordingConsent = null,
        public ?DateTimeImmutable $recordingConsentAt = null,
        public ?string $candidatePhone = null,
        public ?int $id = null,
        public ?string $note = null,
        public string $calendarUid = '',
        public int $calendarSequence = 0,
        /** The work location the onsite address was copied from (`company_locations`); null for free text or another format. The text in `location` stays as it was sent. */
        public ?int $locationId = null,
        /** Why the job seeker proposed another time (required to move a confirmed interview) and when; both null without an open proposal. */
        public ?string $proposalReason = null,
        public ?DateTimeImmutable $proposalMadeAt = null,
        /** The team's own note on a no-show; never shown to the job seeker. */
        public ?string $noShowNote = null,
        /** When the job seeker was reminded that the interview starts within two hours; cleared when the time changes. */
        public ?DateTimeImmutable $candidateSoonRemindedAt = null,
    ) {}

    /**
     * A new pending interview. An online interview needs a meeting link and an onsite one a location; the other is dropped, and a video or voice interview has neither.
     * Only an interview held in the platform (video or voice) can be recorded.
     *
     * @throws BusinessRuleViolation `invalid-interview`
     */
    public static function schedule(
        int $applicationId,
        DateTimeImmutable $startsAt,
        int $durationMinutes,
        InterviewFormat $format,
        ?string $location,
        ?string $meetingUrl,
        int $interviewerId,
        int $createdBy,
        DateTimeImmutable $now,
        ?int $kitId = null,
        ?string $kitName = null,
        bool $recorded = false,
        ?string $note = null,
        string $calendarUid = '',
        int $calendarSequence = 0,
        ?int $locationId = null,
    ): self {
        $location = self::text($location);
        $meetingUrl = self::text($meetingUrl);
        $note = self::text($note);

        $errors = match (true) {
            $startsAt <= $now => ['starts_at' => 'The interview must start in the future.'],
            $durationMinutes < self::DURATION_MIN || $durationMinutes > self::DURATION_MAX => ['duration_minutes' => 'The duration must be between '.self::DURATION_MIN.' and '.self::DURATION_MAX.' minutes.'],
            $format === InterviewFormat::Onsite && $location === null => ['location' => 'Say where the interview takes place.'],
            $format === InterviewFormat::Online && $meetingUrl === null => ['meeting_url' => 'Give the meeting link.'],
            $location !== null && mb_strlen($location) > self::LOCATION_MAX => ['location' => 'The location can have at most '.self::LOCATION_MAX.' characters.'],
            $meetingUrl !== null && mb_strlen($meetingUrl) > self::URL_MAX => ['meeting_url' => 'The link can have at most '.self::URL_MAX.' characters.'],
            $recorded && ! $format->isHeldInPlatform() => ['recorded' => 'Only an interview held in the platform can be recorded.'],
            $note !== null && mb_strlen($note) > self::NOTE_MAX => ['note' => 'The note can have at most '.self::NOTE_MAX.' characters.'],
            default => [],
        };

        if ($errors !== []) {
            $message = (string) reset($errors);

            throw new BusinessRuleViolation($message, 'invalid-interview', extensions: ['errors' => array_map(static fn (string $text): array => [$text], $errors)]);
        }

        return new self(
            $applicationId,
            $startsAt,
            $startsAt->add(new DateInterval('PT'.$durationMinutes.'M')),
            $format,
            $format === InterviewFormat::Onsite ? $location : null,
            $format === InterviewFormat::Online ? $meetingUrl : null,
            $interviewerId,
            $createdBy,
            InterviewStatus::Pending,
            0,
            null,
            $now,
            null,
            $kitId,
            $kitName,
            $recorded,
            note: $note,
            calendarUid: $calendarUid,
            calendarSequence: $calendarSequence,
            locationId: $format === InterviewFormat::Onsite ? $locationId : null,
        );
    }

    public function durationMinutes(): int
    {
        return intdiv($this->endsAt->getTimestamp() - $this->startsAt->getTimestamp(), 60);
    }

    public function hasProposal(): bool
    {
        return $this->proposedStartsAt !== null;
    }

    /**
     * Until when the job seeker may ask to move the interview: the start less the cut-off, for a confirmed interview without an open proposal and with
     * proposals left; null otherwise. It may already be past.
     */
    public function rescheduleUntil(int $cutoffHours): ?DateTimeImmutable
    {
        if ($this->status !== InterviewStatus::Confirmed || $this->hasProposal() || $this->proposalsUsed >= self::PROPOSALS_MAX) {
            return null;
        }

        return $this->startsAt->sub(new DateInterval('PT'.max(0, $cutoffHours).'H'));
    }

    /**
     * The job seeker confirms. For a recorded interview they also answer the recording (granted or refused); the answer is ignored otherwise. For a voice
     * interview they give the number to call (`$phone`, required); for a video interview they may give a fallback number (`$fallbackPhone`); other formats ignore both.
     *
     * @throws StateConflict `interview-not-pending` or `proposal-pending`
     * @throws BusinessRuleViolation `recording-consent-required` or `invalid-phone`
     */
    public function confirm(?RecordingConsent $consent, DateTimeImmutable $now, ?string $phone = null, ?string $fallbackPhone = null): self
    {
        $this->assertAwaitingJobSeeker();

        return $this->consented($consent, $now)->changed(status: InterviewStatus::Confirmed, candidatePhone: $this->confirmedPhone($phone, $fallbackPhone));
    }

    /**
     * The job seeker cannot come: allowed while the interview is pending or confirmed.
     *
     * @throws StateConflict `interview-not-active`
     */
    public function decline(): self
    {
        $this->assertActive();

        return $this->changed(status: InterviewStatus::Declined, proposedStartsAt: null, proposalReason: null, proposalMadeAt: null, calendarSequence: $this->calendarSequence + 1);
    }

    /**
     * The job seeker proposes another start (the duration stays); the status does not change until the team answers. On a pending interview the reason is
     * optional. On a confirmed one it is required and the request must come at least `$cutoffHours` before the current start, for a start at least as far
     * ahead; the interview stays confirmed at the old time, and the recording answer and the phone number given at the confirmation stay.
     *
     * @throws StateConflict `interview-not-pending`, `proposal-pending`, `proposal-limit` or `reschedule-too-late`
     * @throws BusinessRuleViolation `invalid-interview` when the time is not in the future (or within the cut-off) or the reason is too long, `reason-required`,
     *                               `recording-consent-required` or `invalid-phone`
     */
    public function propose(
        DateTimeImmutable $startsAt,
        ?RecordingConsent $consent,
        DateTimeImmutable $now,
        ?string $phone = null,
        ?string $fallbackPhone = null,
        ?string $reason = null,
        int $cutoffHours = 0,
    ): self {
        if (! $this->status->isActive()) {
            throw new StateConflict('This interview is not waiting for your answer.', 'interview-not-pending');
        }

        if ($this->hasProposal()) {
            throw new StateConflict('Your proposed time is waiting for an answer from the company.', 'proposal-pending');
        }

        if ($this->proposalsUsed >= self::PROPOSALS_MAX) {
            throw new StateConflict('You already proposed another time '.self::PROPOSALS_MAX.' times. Confirm or decline this interview.', 'proposal-limit');
        }

        $confirmed = $this->status === InterviewStatus::Confirmed;
        $reason = self::text($reason);
        $cutoff = new DateInterval('PT'.max(0, $cutoffHours).'H');

        if ($confirmed && $reason === null) {
            $message = 'Say why you ask to move the interview.';

            throw new BusinessRuleViolation($message, 'reason-required', extensions: ['errors' => ['reason' => [$message]]]);
        }

        if ($reason !== null && mb_strlen($reason) > self::PROPOSAL_REASON_MAX) {
            $message = 'The reason can have at most '.self::PROPOSAL_REASON_MAX.' characters.';

            throw new BusinessRuleViolation($message, 'invalid-interview', extensions: ['errors' => ['reason' => [$message]]]);
        }

        if ($confirmed && $now > $this->startsAt->sub($cutoff)) {
            throw new StateConflict(
                'The interview can no longer be moved: ask at least '.$cutoffHours.' hours before it starts. You can still decline it or write to the company.',
                'reschedule-too-late',
                extensions: ['cutoff_hours' => $cutoffHours],
            );
        }

        $earliest = $confirmed ? $now->add($cutoff) : $now;

        if ($startsAt <= $now || ($confirmed && $startsAt < $earliest)) {
            $message = $startsAt <= $now ? 'The proposed time must be in the future.' : 'The proposed time must be at least '.$cutoffHours.' hours ahead.';

            throw new BusinessRuleViolation($message, 'invalid-interview', extensions: ['errors' => ['starts_at' => [$message]]]);
        }

        $proposed = $this->changed(proposalsUsed: $this->proposalsUsed + 1, proposedStartsAt: $startsAt, proposalReason: $reason, proposalMadeAt: $now);

        if ($confirmed) {
            return $proposed;
        }

        // A recorded interview takes the answer now, and a voice interview the number to call: the team accepting the proposal confirms it without the job
        // seeker answering again.
        return $proposed->consented($consent, $now)->changed(candidatePhone: $this->confirmedPhone($phone, $fallbackPhone));
    }

    /**
     * The job seeker takes back a consent they gave. Recording that already started cannot be undone by this (the application layer checks that).
     *
     * @throws StateConflict `interview-not-active` or `no-recording-consent`
     */
    public function withdrawRecordingConsent(DateTimeImmutable $now): self
    {
        $this->assertActive();

        if (! $this->recorded || $this->recordingConsent !== RecordingConsent::Granted) {
            throw new StateConflict('You have not given consent to record this interview.', 'no-recording-consent');
        }

        return $this->changed(recordingConsent: RecordingConsent::Withdrawn, recordingConsentAt: $now);
    }

    /**
     * The team takes the proposed time: both sides agreed, so the interview is confirmed.
     *
     * @throws StateConflict `interview-not-active` or `no-proposal`
     */
    public function acceptProposal(): self
    {
        $this->assertActive();

        if ($this->proposedStartsAt === null) {
            throw new StateConflict('There is no proposed time to accept.', 'no-proposal');
        }

        $duration = $this->startsAt->diff($this->endsAt);

        return $this->changed(
            startsAt: $this->proposedStartsAt,
            endsAt: $this->proposedStartsAt->add($duration),
            status: InterviewStatus::Confirmed,
            proposedStartsAt: null,
            remindedAt: null,
            proposalReason: null,
            proposalMadeAt: null,
            candidateSoonRemindedAt: null,
            calendarSequence: $this->calendarSequence + 1,
        );
    }

    /**
     * The team keeps the current time: the proposal is cleared and its use of the quota stays. A pending interview stays pending, a confirmed one keeps its time.
     *
     * @throws StateConflict `interview-not-active` or `no-proposal`
     */
    public function refuseProposal(): self
    {
        $this->assertActive();

        if ($this->proposedStartsAt === null) {
            throw new StateConflict('There is no proposed time to refuse.', 'no-proposal');
        }

        return $this->changed(proposedStartsAt: null, proposalReason: null, proposalMadeAt: null);
    }

    /**
     * The team records that the job seeker did not come: only a confirmed interview that has started. The note is for the team only.
     *
     * @throws StateConflict `interview-not-confirmed` or `interview-not-started`
     * @throws BusinessRuleViolation `invalid-interview` when the note is too long
     */
    public function markNoShow(?string $note, DateTimeImmutable $now): self
    {
        if ($this->status !== InterviewStatus::Confirmed) {
            throw new StateConflict('Only a confirmed interview can be marked as a no-show.', 'interview-not-confirmed');
        }

        if ($now < $this->startsAt) {
            throw new StateConflict('The interview has not started yet.', 'interview-not-started');
        }

        $note = self::text($note);

        if ($note !== null && mb_strlen($note) > self::NOTE_MAX) {
            $message = 'The note can have at most '.self::NOTE_MAX.' characters.';

            throw new BusinessRuleViolation($message, 'invalid-interview', extensions: ['errors' => ['note' => [$message]]]);
        }

        return $this->changed(status: InterviewStatus::NoShow, proposedStartsAt: null, proposalReason: null, proposalMadeAt: null, noShowNote: $note);
    }

    /** @throws StateConflict `interview-not-active` */
    public function cancel(): self
    {
        $this->assertActive();

        return $this->changed(status: InterviewStatus::Cancelled, proposedStartsAt: null, proposalReason: null, proposalMadeAt: null, calendarSequence: $this->calendarSequence + 1);
    }

    /**
     * A confirmed interview took place; a no-show marked by mistake is corrected the same way.
     *
     * @throws StateConflict `interview-not-confirmed`
     */
    public function complete(): self
    {
        if ($this->status !== InterviewStatus::Confirmed && $this->status !== InterviewStatus::NoShow) {
            throw new StateConflict('Only a confirmed interview can be marked as completed.', 'interview-not-confirmed');
        }

        return $this->changed(status: InterviewStatus::Completed, proposedStartsAt: null, proposalReason: null, proposalMadeAt: null);
    }

    /**
     * Only the note for the candidate changes: the status, the time and the proposals stay, and the calendar event is updated in place.
     *
     * @throws StateConflict `interview-not-active`
     * @throws BusinessRuleViolation `invalid-interview`
     */
    public function withNote(?string $note): self
    {
        $this->assertActive();
        $note = self::text($note);

        if ($note !== null && mb_strlen($note) > self::NOTE_MAX) {
            $message = 'The note can have at most '.self::NOTE_MAX.' characters.';

            throw new BusinessRuleViolation($message, 'invalid-interview', extensions: ['errors' => ['note' => [$message]]]);
        }

        return $this->changed(note: $note, noteChanged: true, calendarSequence: $this->calendarSequence + 1);
    }

    public function reminded(DateTimeImmutable $at): self
    {
        return $this->changed(remindedAt: $at);
    }

    /** The job seeker was told that the interview starts within two hours. */
    public function remindedSoon(DateTimeImmutable $at): self
    {
        return $this->changed(candidateSoonRemindedAt: $at);
    }

    /** @throws StateConflict */
    private function assertActive(): void
    {
        if (! $this->status->isActive()) {
            throw new StateConflict('This interview is no longer active.', 'interview-not-active');
        }
    }

    /** @throws StateConflict */
    private function assertAwaitingJobSeeker(): void
    {
        if ($this->status !== InterviewStatus::Pending) {
            throw new StateConflict('This interview is not waiting for your answer.', 'interview-not-pending');
        }

        if ($this->hasProposal()) {
            throw new StateConflict('Your proposed time is waiting for an answer from the company.', 'proposal-pending');
        }
    }

    /** @throws BusinessRuleViolation `recording-consent-required` */
    private function consented(?RecordingConsent $consent, DateTimeImmutable $now): self
    {
        if (! $this->recorded) {
            return $this;
        }

        if (! in_array($consent, [RecordingConsent::Granted, RecordingConsent::Refused], true)) {
            $message = 'Say whether you agree to the recording of this interview.';

            throw new BusinessRuleViolation($message, 'recording-consent-required', extensions: ['errors' => ['recording_consent' => [$message]]]);
        }

        return $this->changed(recordingConsent: $consent, recordingConsentAt: $now);
    }

    /**
     * The number kept for the interview: the one to call for a voice interview (required), the optional fallback for a video interview, none otherwise.
     *
     * @throws BusinessRuleViolation `invalid-phone`
     */
    private function confirmedPhone(?string $phone, ?string $fallbackPhone): ?string
    {
        [$given, $field] = match ($this->format) {
            InterviewFormat::Voice => [$phone, 'phone'],
            InterviewFormat::Video => [$fallbackPhone, 'fallback_phone'],
            default => [null, null],
        };

        if ($field === null || (($given === null || trim($given) === '') && $this->format === InterviewFormat::Video)) {
            return null;
        }

        $number = $given === null ? null : PhoneNumber::normalise($given);

        if ($number === null) {
            $message = $given === null || trim($given) === '' ? 'Give the number to call.' : 'Give an Indonesian phone number, for example 0812 3456 7890.';

            throw new BusinessRuleViolation($message, 'invalid-phone', extensions: ['errors' => [$field => [$message]]]);
        }

        return $number;
    }

    private static function text(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function changed(
        ?DateTimeImmutable $startsAt = null,
        ?DateTimeImmutable $endsAt = null,
        ?InterviewStatus $status = null,
        ?int $proposalsUsed = null,
        mixed $proposedStartsAt = false,
        mixed $remindedAt = false,
        ?RecordingConsent $recordingConsent = null,
        ?DateTimeImmutable $recordingConsentAt = null,
        ?string $candidatePhone = null,
        ?string $note = null,
        bool $noteChanged = false,
        ?int $calendarSequence = null,
        mixed $proposalReason = false,
        mixed $proposalMadeAt = false,
        mixed $noShowNote = false,
        mixed $candidateSoonRemindedAt = false,
    ): self {
        return new self(
            $this->applicationId,
            $startsAt ?? $this->startsAt,
            $endsAt ?? $this->endsAt,
            $this->format,
            $this->location,
            $this->meetingUrl,
            $this->interviewerId,
            $this->createdBy,
            $status ?? $this->status,
            $proposalsUsed ?? $this->proposalsUsed,
            $proposedStartsAt === false ? $this->proposedStartsAt : $proposedStartsAt,
            $this->createdAt,
            $remindedAt === false ? $this->remindedAt : $remindedAt,
            $this->kitId,
            $this->kitName,
            $this->recorded,
            $recordingConsent ?? $this->recordingConsent,
            $recordingConsentAt ?? $this->recordingConsentAt,
            $candidatePhone ?? $this->candidatePhone,
            $this->id,
            $noteChanged ? $note : $this->note,
            $this->calendarUid,
            $calendarSequence ?? $this->calendarSequence,
            $this->locationId,
            $proposalReason === false ? $this->proposalReason : $proposalReason,
            $proposalMadeAt === false ? $this->proposalMadeAt : $proposalMadeAt,
            $noShowNote === false ? $this->noShowNote : $noShowNote,
            $candidateSoonRemindedAt === false ? $this->candidateSoonRemindedAt : $candidateSoonRemindedAt,
        );
    }
}
