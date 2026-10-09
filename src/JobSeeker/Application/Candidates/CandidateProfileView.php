<?php

namespace Src\JobSeeker\Application\Candidates;

use DateTimeImmutable;
use Src\JobSeeker\Domain\Education;
use Src\JobSeeker\Domain\JobSeekerProfile;
use Src\JobSeeker\Domain\Resume;
use Src\JobSeeker\Domain\WorkExperience;

/**
 * A candidate's profile as a company finds it: no gender, religion, nationality, marital status or birth date, and the contact data (`contact`) only when the
 * member already sees an application of the candidate. A master value is `{id, name}`.
 */
final readonly class CandidateProfileView
{
    /**
     * @param  array{id: int, name: string|null}|null  $province
     * @param  array{id: int, name: string|null}|null  $city
     * @param  array{id: int, name: string|null}|null  $experienceLevel
     * @param  array{amount: int, period: array{id: int, name: string|null}|null}|null  $expectedSalary
     * @param  list<array{entry: WorkExperience, job_type: array{id: int, name: string|null}|null}>  $experiences
     * @param  list<array{entry: Education, level: array{id: int, name: string|null}}>  $educations
     * @param  list<array{id: int, name: string|null}>  $skills
     * @param  list<array{id: int, name: string|null, proficiency: string}>  $languages
     * @param  list<Resume>  $resumes  only when the candidate lets companies see them
     * @param  array{email: string|null, phone: string|null, whatsapp: string|null, address_line: string|null, links: list<array{type: string, url: string}>}|null  $contact
     */
    public function __construct(
        public string $slug,
        public string $name,
        public bool $hasAvatar,
        public JobSeekerProfile $profile,
        public ?array $province,
        public ?array $city,
        public ?array $experienceLevel,
        public ?array $expectedSalary,
        public array $experiences,
        public array $educations,
        public array $skills,
        public array $languages,
        public array $resumes,
        public ?array $contact,
        public DateTimeImmutable $updatedAt,
    ) {}
}
