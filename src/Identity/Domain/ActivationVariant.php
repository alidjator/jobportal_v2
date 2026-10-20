<?php

namespace Src\Identity\Domain;

/** Which text an imported account gets: a company owner, a job seeker who can sign in with the old password, or a job seeker who has to set a new one. */
enum ActivationVariant: string
{
    case HasPassword = 'has_password';
    case NoPassword = 'no_password';
    case Company = 'company';

    public static function of(User $user): self
    {
        return match (true) {
            $user->isCompanyMember() => self::Company,
            $user->passwordHash() === null => self::NoPassword,
            default => self::HasPassword,
        };
    }

    /** @return list<self> */
    public static function ofAudience(UserType $audience): array
    {
        return $audience === UserType::CompanyMember ? [self::Company] : [self::HasPassword, self::NoPassword];
    }
}
