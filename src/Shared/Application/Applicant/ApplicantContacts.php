<?php

namespace Src\Shared\Application\Applicant;

/** The applicant's account data that belongs to Identity. Provided by Identity. */
interface ApplicantContacts
{
    /** The applicant's contact, or null when there is no account or it was anonymised (nobody is told anything then). */
    public function contact(int $userId): ?ApplicantContact;

    /** Whether the account exists, is a job seeker's and was not anonymised: the only accounts a company may start a conversation with. */
    public function isActiveJobSeeker(int $userId): bool;
}
