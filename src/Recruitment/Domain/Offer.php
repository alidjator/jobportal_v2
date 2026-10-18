<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A job offer of one application: position, monthly salary in Rupiah, start date, note and terms (the offers sent before the terms existed have none), open for an answer until its deadline. The job seeker accepts or
 * declines it; the team may withdraw it, and the hourly command expires it. An application has at most one pending offer.
 */
final readonly class Offer
{
    public const int POSITION_MAX = 150;

    public const int NOTE_MAX = 2000;

    public const int ANSWER_NOTE_MAX = 1000;

    public const int SALARY_MAX = 100_000_000_000;

    public const int VALID_DAYS_MAX = 30;

    private const string ZONE = 'Asia/Jakarta';

    public function __construct(
        public int $applicationId,
        public string $position,
        public int $salary,
        public DateTimeImmutable $startDate,
        public ?string $note,
        public DateTimeImmutable $expiresAt,
        public int $createdBy,
        public OfferStatus $status,
        public ?string $answerNote,
        public ?DateTimeImmutable $answeredAt,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $remindedAt = null,
        public ?OfferTerms $terms = null,
        public ?OfferLetter $letter = null,
        public ?int $id = null,
    ) {}

    /**
     * A new pending offer. The start date is a calendar day that is not before today (Jakarta); the deadline is in the future and at most {@see self::VALID_DAYS_MAX} days away.
     *
     * @throws BusinessRuleViolation `invalid-offer`
     */
    public static function make(
        int $applicationId,
        string $position,
        int $salary,
        DateTimeImmutable $startDate,
        ?string $note,
        DateTimeImmutable $expiresAt,
        int $createdBy,
        DateTimeImmutable $now,
        ?OfferTerms $terms = null,
    ): self {
        $position = trim($position);
        $note = self::text($note);
        $today = $now->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d');

        $errors = match (true) {
            $position === '' => ['position' => 'Give the position that is offered.'],
            mb_strlen($position) > self::POSITION_MAX => ['position' => 'The position can have at most '.self::POSITION_MAX.' characters.'],
            $salary < 1 || $salary > self::SALARY_MAX => ['salary' => 'The monthly salary must be between 1 and '.self::SALARY_MAX.' Rupiah.'],
            $startDate->format('Y-m-d') < $today => ['start_date' => 'The start date cannot be in the past.'],
            $note !== null && mb_strlen($note) > self::NOTE_MAX => ['note' => 'The note can have at most '.self::NOTE_MAX.' characters.'],
            $expiresAt <= $now => ['expires_at' => 'The deadline must be in the future.'],
            $expiresAt > $now->modify('+'.self::VALID_DAYS_MAX.' days') => ['expires_at' => 'The deadline can be at most '.self::VALID_DAYS_MAX.' days away.'],
            default => [],
        };

        if ($errors !== []) {
            $message = (string) reset($errors);

            throw new BusinessRuleViolation($message, 'invalid-offer', extensions: ['errors' => array_map(static fn (string $text): array => [$text], $errors)]);
        }

        return new self($applicationId, $position, $salary, $startDate->setTime(0, 0), $note, $expiresAt, $createdBy, OfferStatus::Pending, null, null, $now, terms: $terms);
    }

    /** The status as of `$now`: a pending offer past its deadline is expired even before the hourly command marks it. */
    public function statusAt(DateTimeImmutable $now): OfferStatus
    {
        return $this->status === OfferStatus::Pending && $this->expiresAt <= $now ? OfferStatus::Expired : $this->status;
    }

    public function isOpenAt(DateTimeImmutable $now): bool
    {
        return $this->statusAt($now) === OfferStatus::Pending;
    }

    /** @throws StateConflict `offer-not-pending` or `offer-expired` */
    public function accept(DateTimeImmutable $now): self
    {
        $this->assertOpen($now);

        return $this->changed(status: OfferStatus::Accepted, answeredAt: $now);
    }

    /** @throws StateConflict `offer-not-pending` or `offer-expired` */
    public function decline(?string $note, DateTimeImmutable $now): self
    {
        $this->assertOpen($now);

        return $this->changed(status: OfferStatus::Declined, answerNote: self::text($note), answeredAt: $now);
    }

    /** @throws StateConflict `offer-not-pending` */
    public function withdraw(): self
    {
        if ($this->status !== OfferStatus::Pending) {
            throw new StateConflict('This offer is no longer pending.', 'offer-not-pending');
        }

        return $this->changed(status: OfferStatus::Withdrawn);
    }

    public function expire(): self
    {
        return $this->changed(status: OfferStatus::Expired);
    }

    public function reminded(DateTimeImmutable $at): self
    {
        return $this->changed(remindedAt: $at);
    }

    /** The offer with its letter (made once the offer has an id, so the file can be kept under it). */
    public function withLetter(OfferLetter $letter): self
    {
        return $this->changed(letter: $letter);
    }

    /** @throws StateConflict */
    private function assertOpen(DateTimeImmutable $now): void
    {
        match ($this->statusAt($now)) {
            OfferStatus::Pending => null,
            OfferStatus::Expired => throw new StateConflict('This offer has expired.', 'offer-expired'),
            default => throw new StateConflict('This offer is no longer pending.', 'offer-not-pending'),
        };
    }

    private static function text(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function changed(
        ?OfferStatus $status = null,
        mixed $answerNote = false,
        mixed $answeredAt = false,
        mixed $remindedAt = false,
        ?OfferLetter $letter = null,
    ): self {
        return new self(
            $this->applicationId,
            $this->position,
            $this->salary,
            $this->startDate,
            $this->note,
            $this->expiresAt,
            $this->createdBy,
            $status ?? $this->status,
            $answerNote === false ? $this->answerNote : $answerNote,
            $answeredAt === false ? $this->answeredAt : $answeredAt,
            $this->createdAt,
            $remindedAt === false ? $this->remindedAt : $remindedAt,
            $this->terms,
            $letter ?? $this->letter,
            $this->id,
        );
    }
}
