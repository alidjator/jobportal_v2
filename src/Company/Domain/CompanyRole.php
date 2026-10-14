<?php

namespace Src\Company\Domain;

/** A company admin holds every permission and manages the team; a member holds the permissions checked for them (`MemberPreset` names the usual sets). */
enum CompanyRole: string
{
    case CompanyAdmin = 'company_admin';
    case Member = 'member';
}
