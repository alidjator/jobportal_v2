<?php

namespace Src\Shared\Interface\Http\Requests;

use DateTimeImmutable;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;

/**
 * The fields of a test sent to a candidate, shared by the request that sends it and the move requests (where they sit under `assessment`). Times are
 * ISO 8601 with an offset or `Z`; whether the window is acceptable and whether the test and the assessors may be used are use case rules (422).
 */
final class AssessmentInvitationFields
{
    private const string TIME_FORMAT = 'Y-m-d\TH:i:sp';

    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix = '', string $presence = 'required'): array
    {
        return [
            $prefix.'test_id' => [$presence, 'integer', 'min:1'],
            $prefix.'starts_at' => ['nullable', 'date_format:'.self::TIME_FORMAT],
            $prefix.'due_at' => [$presence, 'date_format:'.self::TIME_FORMAT],
            $prefix.'assessor_ids' => ['nullable', 'array', 'max:50'],
            $prefix.'assessor_ids.*' => ['integer', 'min:1'],
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function input(array $data): AssessmentInvitationInput
    {
        /** @var list<int|string> $assessors */
        $assessors = is_array($data['assessor_ids'] ?? null) ? $data['assessor_ids'] : [];

        return new AssessmentInvitationInput(
            (int) $data['test_id'],
            isset($data['starts_at']) ? self::time((string) $data['starts_at']) : null,
            self::time((string) $data['due_at']),
            array_map(intval(...), $assessors),
        );
    }

    /** The input of the validated `assessment` object, or null when the request has none. */
    public static function inputOrNull(mixed $data): ?AssessmentInvitationInput
    {
        return is_array($data) && $data !== [] ? self::input($data) : null;
    }

    private static function time(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::TIME_FORMAT, $value) ?: new DateTimeImmutable($value);
    }
}
