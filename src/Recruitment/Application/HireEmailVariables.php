<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\HireDetails;

/** The `join_date` and `note` variables of the hired emails: each is empty without a value, otherwise on its own lines with its label in the recipient's language. */
final class HireEmailVariables
{
    /** @return array{join_date: string, note: string} */
    public static function for(?HireDetails $hire, string $locale): array
    {
        $indonesian = $locale === 'id';

        return [
            'join_date' => $hire === null ? '' : "\n\n".($indonesian ? 'Tanggal bergabung' : 'Join date').': '.$hire->joinDate->format('Y-m-d'),
            'note' => $hire === null || $hire->note === null ? '' : "\n\n".($indonesian ? 'Catatan dari perusahaan' : 'A note from the company').': '.$hire->note,
        ];
    }
}
