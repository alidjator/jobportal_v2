<?php

namespace Src\Identity\Domain;

enum UserType: string
{
    case JobSeeker = 'job_seeker';
    case CompanyMember = 'company_member';
    case Psychologist = 'psychologist';
}
