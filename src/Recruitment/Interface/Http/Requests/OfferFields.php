<?php

namespace Src\Recruitment\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Application\OfferInput;
use Src\Recruitment\Domain\Offer;

/**
 * The offer fields shared by the send request and the move request (where they sit under `offer`). The deadline is ISO 8601 with an offset or `Z`, the start
 * date a plain day. Ranges and dates are domain rules (422 `invalid-offer`); a position left out defaults to the job title.
 */
final class OfferFields
{
    private const string TIME_FORMAT = 'Y-m-d\TH:i:sp';

    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix = '', string $presence = 'required'): array
    {
        return [
            $prefix.'position' => ['nullable', 'string', 'max:'.Offer::POSITION_MAX],
            $prefix.'salary' => [$presence, 'integer'],
            $prefix.'start_date' => [$presence, 'date_format:Y-m-d'],
            $prefix.'note' => ['nullable', 'string', 'max:'.Offer::NOTE_MAX],
            $prefix.'expires_at' => [$presence, 'date_format:'.self::TIME_FORMAT],
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function input(array $data): OfferInput
    {
        $expires = (string) $data['expires_at'];

        return new OfferInput(
            isset($data['position']) ? (string) $data['position'] : null,
            (int) $data['salary'],
            new DateTimeImmutable((string) $data['start_date'], new DateTimeZone('UTC')),
            isset($data['note']) ? (string) $data['note'] : null,
            DateTimeImmutable::createFromFormat(self::TIME_FORMAT, $expires) ?: new DateTimeImmutable($expires),
        );
    }
}
