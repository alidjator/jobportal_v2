<?php

namespace Src\JobSeeker\Domain;

/** The values match the check constraint of `jobseeker_profiles.marital_status`. */
enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';
}
