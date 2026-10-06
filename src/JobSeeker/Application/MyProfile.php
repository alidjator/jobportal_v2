<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\Education;
use Src\JobSeeker\Domain\JobSeekerProfile;
use Src\JobSeeker\Domain\LanguageProficiency;
use Src\JobSeeker\Domain\ProfileCompleteness;
use Src\JobSeeker\Domain\ProfileLink;
use Src\JobSeeker\Domain\Resume;
use Src\JobSeeker\Domain\WorkExperience;

/** The profile as the owner sees it, with its lists and whether it is complete enough to apply. */
final readonly class MyProfile
{
    /**
     * @param  list<WorkExperience>  $experiences
     * @param  list<Education>  $educations
     * @param  list<array{id: int, name: string|null}>  $skills  in their order, the name in the request language
     * @param  list<array{id: int, name: string|null, proficiency: LanguageProficiency}>  $languages
     * @param  list<ProfileLink>  $links
     * @param  list<Resume>  $resumes  the newest first
     */
    public function __construct(
        public JobSeekerProfile $profile,
        public ProfileCompleteness $completeness,
        public array $experiences,
        public array $educations,
        public array $skills,
        public array $languages,
        public array $links,
        public array $resumes,
    ) {}
}
