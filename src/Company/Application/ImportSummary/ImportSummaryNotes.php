<?php

namespace Src\Company\Application\ImportSummary;

/** The notes of the import summary that are true for every imported company, as codes (the client writes and translates the text). Not stored: a change needs no new import. */
final class ImportSummaryNotes
{
    /** @return list<string> */
    public static function codes(): array
    {
        return [
            'terms_again',
            'two_factor_enrolment',
            'no_verification_documents',
            'contact_fields_empty',
            'team_members_not_carried',
            'screening_answers_not_carried',
            'imported_applications_explained',
            'saved_candidates_private_list',
        ];
    }
}
