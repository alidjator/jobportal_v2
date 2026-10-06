<?php

namespace Src\JobSeeker\Domain;

/** Whether the job seeker is looking for work. The values match the check constraint of `jobseeker_profiles.availability`. */
enum Availability: string
{
    case OpenToWork = 'open_to_work';
    case EmployedOpenToOffers = 'employed_open_to_offers';
    case NotLooking = 'not_looking';
}
