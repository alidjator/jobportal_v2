<?php

namespace Src\JobSeeker\Domain;

/** What a company member opened: the profile or a resume file. */
enum ProfileViewKind: string
{
    case Profile = 'profile';
    case Resume = 'resume';
}
