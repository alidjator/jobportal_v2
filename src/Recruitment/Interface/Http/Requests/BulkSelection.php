<?php

namespace Src\Recruitment\Interface\Http\Requests;

/** The `application_ids` field of the bulk requests: a list of distinct ids, in the order the client wants them processed. */
final class BulkSelection
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'application_ids' => ['required', 'array', 'min:1'],
            'application_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /** @return list<int> */
    public static function ids(mixed $validated): array
    {
        return array_map('intval', array_values((array) $validated));
    }
}
