<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Src\Recruitment\Domain\HireDetails;

/** The `hire` object of an application in the API: the join date and the note for the candidate; null when the application has none. */
final class HirePayload
{
    /** @return array{join_date: string, note: string|null}|null */
    public static function of(?HireDetails $hire): ?array
    {
        return $hire === null ? null : ['join_date' => $hire->joinDate->format('Y-m-d'), 'note' => $hire->note];
    }
}
