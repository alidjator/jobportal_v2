<?php

namespace Src\Company\Domain;

/** What a member may do in their company: a company admin does everything a recruiter can, and also manages the team and the company profile. */
enum CompanyRole: string
{
    case CompanyAdmin = 'company_admin';
    case Recruiter = 'recruiter';
}
