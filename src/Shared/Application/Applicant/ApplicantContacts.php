<?php

namespace Src\Shared\Application\Applicant;

/** The applicant's account data that belongs to Identity. Provided by Identity. */
interface ApplicantContacts
{
    /** The applicant's contact, or null when there is no account or it was anonymised (nobody is told anything then). */
    public function contact(int $userId): ?ApplicantContact;

    /**
     * The contacts of these accounts, keyed by account id; an account that does not exist or was anonymised is missing. One read for the whole list.
     *
     * @param  list<int>  $userIds
     * @return array<int, ApplicantContact>
     */
    public function contacts(array $userIds): array;

    /** Whether the account exists, is a job seeker's and was not anonymised: the only accounts a company may start a conversation with. */
    public function isActiveJobSeeker(int $userId): bool;

    /**
     * The names of these accounts, keyed by account id; an account that does not exist or was anonymised is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    public function names(array $userIds): array;

    /**
     * The accounts among these whose address is on the suppression list (it no longer receives email); an account without an address on file is not flagged. One read for the whole list.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function undeliverable(array $userIds): array;
}
