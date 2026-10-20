<?php

namespace Src\JobSeeker\Application\ImportSummary;

/** The notes of the import summary that are true for every imported job seeker, as codes (the client writes and translates the text). Not stored: a change needs no new import. */
final class ImportSummaryNotes
{
    /** @return list<string> */
    public static function codes(): array
    {
        return [
            'terms_again',
            'discoverability_off',
            'language_level_default',
            'chat_history_not_imported',
            'profile_views_not_imported',
            'activation_email_timing',
        ];
    }
}
