<?php

namespace Src\JobSeeker\Domain;

/** The values match the check constraint of `jobseeker_profiles.gender`. */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
}
