<?php

namespace Src\Shared\Application\Applicant;

/** The applicant's account data that belongs to Identity. Provided by Identity. */
interface ApplicantContacts
{
    /** The applicant's contact, or null when there is no account or it was anonymised (nobody is told anything then). */
    public function contact(int $userId): ?ApplicantContact;
}
