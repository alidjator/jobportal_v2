<?php

namespace Src\Content\Domain;

enum AnnouncementAudience: string
{
    case All = 'all';
    case JobSeekers = 'job_seekers';
    case CompanyMembers = 'company_members';
}
