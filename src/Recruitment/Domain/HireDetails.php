<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The join date and the optional note for the candidate of a hired application. */
final readonly class HireDetails
{
    public const int NOTE_MAX = 1000;

    public const int PAST_DAYS_MAX = 90;

    private const string ZONE = 'Asia/Jakarta';

    public function __construct(public DateTimeImmutable $joinDate, public ?string $note) {}

    /**
     * The details of a hire (`$prefix` puts the error fields under a request key, such as `hire.`). The join date is a calendar day from {@see self::PAST_DAYS_MAX} days ago to one year ahead (Jakarta); a blank note is no note.
     *
     * @throws BusinessRuleViolation `join-date-required`, `invalid-join-date` or `invalid-hire-note`
     */
    public static function make(?DateTimeImmutable $joinDate, ?string $note, DateTimeImmutable $now, string $prefix = ''): self
    {
        if (! $joinDate instanceof DateTimeImmutable) {
            self::refuse('join-date-required', 'Give the date the candidate joins.', $prefix.'join_date');
        }

        $today = new DateTimeImmutable($now->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d'), new DateTimeZone('UTC'));
        $day = $joinDate->format('Y-m-d');

        if ($day < $today->modify('-'.self::PAST_DAYS_MAX.' days')->format('Y-m-d') || $day > $today->modify('+1 year')->format('Y-m-d')) {
            self::refuse('invalid-join-date', 'The join date must be within the last '.self::PAST_DAYS_MAX.' days or the next year.', $prefix.'join_date');
        }

        return new self($joinDate->setTime(0, 0), self::cleanNote($note, $prefix));
    }

    /**
     * A note trimmed to null when blank.
     *
     * @throws BusinessRuleViolation `invalid-hire-note`
     */
    public static function cleanNote(?string $note, string $prefix = ''): ?string
    {
        $note = $note === null ? null : trim($note);

        if ($note !== null && mb_strlen($note) > self::NOTE_MAX) {
            self::refuse('invalid-hire-note', 'The note can have at most '.self::NOTE_MAX.' characters.', $prefix.'note');
        }

        return $note === '' ? null : $note;
    }

    /** Whether these are the same details (the date by its day). */
    public function equals(self $other): bool
    {
        return $this->joinDate->format('Y-m-d') === $other->joinDate->format('Y-m-d') && $this->note === $other->note;
    }

    private static function refuse(string $code, string $message, string $field): never
    {
        throw new BusinessRuleViolation($message, $code, extensions: ['errors' => [$field => [$message]]]);
    }
}
