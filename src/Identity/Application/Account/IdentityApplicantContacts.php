<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;

/** Gives Recruitment the name and email of an applicant's account. */
final readonly class IdentityApplicantContacts implements ApplicantContacts
{
    public function __construct(private UserRepository $users) {}

    public function contact(int $userId): ?ApplicantContact
    {
        $user = $this->users->findById($userId);

        return $user === null || $user->anonymizedAt() !== null ? null : new ApplicantContact($user->name(), $user->email(), $user->locale());
    }
}
