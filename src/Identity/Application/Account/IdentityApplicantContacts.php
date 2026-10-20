<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\UserType;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Email\EmailSuppressions;

/** Gives Recruitment the name and email of an applicant's account. */
final readonly class IdentityApplicantContacts implements ApplicantContacts
{
    public function __construct(private UserRepository $users, private EmailSuppressions $suppressions) {}

    public function contact(int $userId): ?ApplicantContact
    {
        $user = $this->users->findById($userId);

        return $user === null || $user->anonymizedAt() !== null ? null : new ApplicantContact($user->name(), $user->email(), $user->locale(), $user->status()->isActive(), $user->isEmailVerified());
    }

    public function contacts(array $userIds): array
    {
        $contacts = [];

        foreach ($this->users->findByIds($userIds) as $user) {
            if ($user->anonymizedAt() === null && $user->id !== null) {
                $contacts[$user->id] = new ApplicantContact($user->name(), $user->email(), $user->locale(), $user->status()->isActive(), $user->isEmailVerified());
            }
        }

        return $contacts;
    }

    public function isActiveJobSeeker(int $userId): bool
    {
        $user = $this->users->findById($userId);

        return $user !== null && $user->anonymizedAt() === null && $user->type === UserType::JobSeeker;
    }

    public function undeliverable(array $userIds): array
    {
        $emails = [];

        foreach ($this->users->findByIds($userIds) as $user) {
            if ($user->anonymizedAt() === null && $user->id !== null) {
                $emails[$user->id] = strtolower($user->email());
            }
        }

        $suppressed = $this->suppressions->reasons(array_values($emails));

        return array_keys(array_filter($emails, static fn (string $email): bool => isset($suppressed[$email])));
    }

    public function names(array $userIds): array
    {
        $names = [];

        foreach ($this->users->findByIds($userIds) as $user) {
            if ($user->anonymizedAt() === null && $user->id !== null) {
                $names[$user->id] = $user->name();
            }
        }

        return $names;
    }
}
