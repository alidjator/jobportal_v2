<?php

namespace Src\JobSeeker\Application\Candidates;

/** The order of the candidate search: the most recently updated profile first, or the most recently active job seeker first (never-seen accounts last). */
enum CandidateSort: string
{
    case UpdatedAtDescending = '-updated_at';
    case LastActiveDescending = '-last_active';
}
