<?php

namespace Src\Shared\Application\Dashboard;

/** Whose dashboard a card belongs to. */
enum DashboardAudience: string
{
    case JobSeeker = 'job_seeker';
    case Company = 'company';
    case Admin = 'admin';
}
