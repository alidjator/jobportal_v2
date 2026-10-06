<?php

namespace Src\Shared\Application\Applicant;

/** The applicant's account data that belongs to Identity. Provided by Identity. */
interface ApplicantContacts
{
    public function contact(int $userId): ?ApplicantContact;
}
