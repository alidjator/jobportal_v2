<?php

namespace Src\Recruitment\Application;

/** One application for the export: the list line plus the contact details of the snapshot (null once the account was anonymised) and the screening answers in the job's order. */
final readonly class ApplicantExportRow
{
    /** @param  list<ApplicationAnswerView>  $answers */
    public function __construct(
        public RecruiterApplicationRow $row,
        public ?string $email,
        public ?string $phone,
        public array $answers,
    ) {}
}
