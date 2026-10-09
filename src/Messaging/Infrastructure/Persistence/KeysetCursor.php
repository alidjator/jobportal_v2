<?php

namespace Src\Messaging\Infrastructure\Persistence;

use JsonException;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The opaque cursor of a keyset page: the sort values of the last row of the previous page, as base64 URL-safe JSON. */
final class KeysetCursor
{
    /** @param  list<string|int>  $parts */
    public static function encode(array $parts): string
    {
        return rtrim(strtr(base64_encode(json_encode($parts, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @return list<string|int>
     *
     * @throws BusinessRuleViolation `invalid-cursor`
     */
    public static function decode(string $cursor, int $count): array
    {
        try {
            $decoded = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw self::invalid();
        }

        if (! is_array($decoded) || ! array_is_list($decoded) || count($decoded) !== $count) {
            throw self::invalid();
        }

        foreach ($decoded as $part) {
            if (! is_string($part) && ! is_int($part)) {
                throw self::invalid();
            }
        }

        return $decoded;
    }

    private static function invalid(): BusinessRuleViolation
    {
        return new BusinessRuleViolation('The cursor is not valid. Start again without a cursor.', 'invalid-cursor');
    }
}
