<?php

namespace Src\Recruitment\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Application\HireInput;
use Src\Recruitment\Domain\HireDetails;

/**
 * The hire details of a request (`hire` in a move, the body of `PATCH .../hire`): a join date and a note for the candidate. The date is required only by the
 * use case, which answers `join-date-required` and `invalid-join-date`.
 */
final class HireFields
{
    /** @return array<string, list<string>> */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'join_date' => ['nullable', 'date_format:Y-m-d'],
            $prefix.'note' => ['nullable', 'string', 'max:'.HireDetails::NOTE_MAX],
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function input(array $data): HireInput
    {
        return new HireInput(
            isset($data['join_date']) ? new DateTimeImmutable((string) $data['join_date'], new DateTimeZone('UTC')) : null,
            isset($data['note']) ? (string) $data['note'] : null,
        );
    }

    /** The `hire` object of a move request, or null when it has none. */
    public static function inputOrNull(mixed $data): ?HireInput
    {
        return is_array($data) ? self::input($data) : null;
    }
}
