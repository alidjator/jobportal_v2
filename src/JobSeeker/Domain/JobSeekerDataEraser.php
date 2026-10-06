<?php

namespace Src\JobSeeker\Domain;

/** Deletes every row JobSeeker holds for a user: the profile, the experiences, the education, the skills, the languages, the links and the resumes. */
interface JobSeekerDataEraser
{
    public function deleteRows(int $userId): void;
}
