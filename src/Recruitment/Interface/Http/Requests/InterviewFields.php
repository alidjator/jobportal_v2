<?php

namespace Src\Recruitment\Interface\Http\Requests;

use DateTimeImmutable;
use Illuminate\Validation\Rule;
use Src\Recruitment\Application\InterviewInput;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewPanel;

/**
 * The interview fields shared by the schedule request and the move request (where they sit under `interview`). Times are ISO 8601 with an offset or `Z`;
 * whether the time is in the future and whether a place or link is given are domain rules (422 `invalid-interview`).
 */
final class InterviewFields
{
    private const string TIME_FORMAT = 'Y-m-d\TH:i:sp';

    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix = '', mixed $presence = 'required'): array
    {
        return [
            $prefix.'starts_at' => [$presence, 'date_format:'.self::TIME_FORMAT],
            $prefix.'duration_minutes' => [$presence, 'integer'],
            $prefix.'format' => [$presence, Rule::enum(InterviewFormat::class)],
            $prefix.'location' => ['nullable', 'string', 'max:'.Interview::LOCATION_MAX],
            // An active work location of the company (`GET company/locations`) for an onsite interview: its address replaces `location`.
            $prefix.'location_id' => ['nullable', 'integer', 'min:1'],
            $prefix.'meeting_url' => ['nullable', 'string', 'url:http,https', 'max:'.Interview::URL_MAX],
            $prefix.'interviewer_id' => [$presence, 'integer', 'min:1'],
            // Omitted: the job's default kit (or the kit of the interview this one replaces); null: no kit.
            $prefix.'kit_id' => ['nullable', 'integer', 'min:1'],
            $prefix.'participant_ids' => ['nullable', 'array', 'max:'.InterviewPanel::EXTRA_MAX],
            $prefix.'participant_ids.*' => ['integer', 'min:1', 'distinct'],
            // Omitted: a new video time keeps the flag of the interview it replaces (a first interview is not recorded). Only a video interview can be recorded.
            $prefix.'recorded' => ['nullable', 'boolean'],
            // The note for the candidate, plain text. Omitted: a new time keeps the earlier note; null clears it.
            $prefix.'note' => ['nullable', 'string', 'max:'.Interview::NOTE_MAX],
        ];
    }

    /** Whether the `interview` part of a move names no schedule but says the time comes later (`schedule_later`). */
    public static function scheduleLater(mixed $interview): bool
    {
        return is_array($interview) && filter_var($interview['schedule_later'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether the schedule fields of a move are required: `interview` is sent and it is not only "schedule later". Together with fields of a schedule, "later" still
     * asks for a whole schedule, so the use case can refuse the mix (422 `invalid-interview`).
     */
    public static function requiredUnlessLater(mixed $interview): bool
    {
        if (! is_array($interview) || $interview === []) {
            return false;
        }

        if (! self::scheduleLater($interview)) {
            return true;
        }

        foreach (['starts_at', 'duration_minutes', 'format', 'interviewer_id', 'location', 'location_id', 'meeting_url'] as $field) {
            if (isset($interview[$field])) {
                return true;
            }
        }

        return false;
    }

    public static function time(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::TIME_FORMAT, $value) ?: new DateTimeImmutable($value);
    }

    /** @param  array<string, mixed>  $data */
    public static function input(array $data): InterviewInput
    {
        return new InterviewInput(
            self::time((string) $data['starts_at']),
            (int) $data['duration_minutes'],
            InterviewFormat::from((string) $data['format']),
            isset($data['location']) ? (string) $data['location'] : null,
            isset($data['meeting_url']) ? (string) $data['meeting_url'] : null,
            (int) $data['interviewer_id'],
            isset($data['kit_id']) ? (int) $data['kit_id'] : null,
            array_key_exists('kit_id', $data),
            isset($data['participant_ids']) ? array_map('intval', array_values($data['participant_ids'])) : null,
            isset($data['recorded']) ? (bool) $data['recorded'] : null,
            isset($data['note']) ? (string) $data['note'] : null,
            array_key_exists('note', $data),
            isset($data['location_id']) ? (int) $data['location_id'] : null,
        );
    }
}
