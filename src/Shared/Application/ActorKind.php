<?php

namespace Src\Shared\Application;

enum ActorKind: string
{
    case JobSeeker = 'job_seeker';
    case CompanyMember = 'company_member';
    case Admin = 'admin';
    case System = 'system';
}
