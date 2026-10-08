<?php

namespace Src\Recruitment\Interface\Http\Requests;

use DateTimeImmutable;
use Illuminate\Validation\Rule;
use Src\Recruitment\Application\InterviewInput;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;

/**
 * The interview fields shared by the schedule request and the move request (where they sit under `interview`). Times are ISO 8601 with an offset or `Z`;
 * whether the time is in the future and whether a place or link is given are domain rules (422 `invalid-interview`).
 */
final class InterviewFields
{
    private const string TIME_FORMAT = 'Y-m-d\TH:i:sp';

    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix = '', string $presence = 'required'): array
    {
        return [
            $prefix.'starts_at' => [$presence, 'date_format:'.self::TIME_FORMAT],
            $prefix.'duration_minutes' => [$presence, 'integer'],
            $prefix.'format' => [$presence, Rule::enum(InterviewFormat::class)],
            $prefix.'location' => ['nullable', 'string', 'max:'.Interview::LOCATION_MAX],
            $prefix.'meeting_url' => ['nullable', 'string', 'url:http,https', 'max:'.Interview::URL_MAX],
            $prefix.'interviewer_id' => [$presence, 'integer', 'min:1'],
        ];
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
        );
    }
}
