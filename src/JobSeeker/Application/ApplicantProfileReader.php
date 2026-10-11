<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Application\Cv\GenerateCv;
use Src\JobSeeker\Domain\Education;
use Src\JobSeeker\Domain\EducationRepository;
use Src\JobSeeker\Domain\ExperienceRepository;
use Src\JobSeeker\Domain\JobSeekerProfile;
use Src\JobSeeker\Domain\ProfileCompleteness;
use Src\JobSeeker\Domain\ProfileListRepository;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\JobSeeker\Domain\WorkExperience;
use Src\Shared\Application\Applicant\ApplicantProfileSource;
use Src\Shared\Application\Applicant\ApplicantResume;
use Src\Shared\Application\Applicant\ApplicantSubmission;
use Src\Shared\Application\Applicant\GeneratedCvSpec;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;

/**
 * Gives Recruitment what an application takes from the profile: the completeness check, the chosen resume file and a snapshot of the profile.
 *
 * The snapshot leaves out gender, religion, marital status, nationality, the birth date and the avatar (decided for applications): it carries the age at
 * the time of applying instead. A master value is stored as its id plus its name in both languages, so the snapshot stays readable whatever happens to the master.
 */
final readonly class ApplicantProfileReader implements ApplicantProfileSource
{
    private const int SNAPSHOT_VERSION = 1;

    private const array LANGUAGES = ['id', 'en'];

    public function __construct(
        private ProfileRepository $profiles,
        private ExperienceRepository $experiences,
        private EducationRepository $educations,
        private ProfileListRepository $lists,
        private ResumeRepository $resumes,
        private FileStorage $files,
        private ReferenceValues $references,
        private LocationReferences $locations,
        private JakartaToday $today,
        private Clock $clock,
        private GenerateCv $cvs,
    ) {}

    public function forApplication(int $userId, int $resumeId): ApplicantSubmission
    {
        $profile = $this->profiles->find($userId) ?? JobSeekerProfile::empty($userId);
        $counts = $this->profiles->counts($userId);
        $completeness = ProfileCompleteness::evaluate($profile, $counts['experiences'], $counts['educations'], $counts['resumes']);

        return new ApplicantSubmission($completeness->missing, $this->resume($userId, $resumeId), $this->snapshot($profile));
    }

    public function generateCv(int $userId, GeneratedCvSpec $cv): ?ApplicantResume
    {
        $profile = $this->profiles->find($userId) ?? JobSeekerProfile::empty($userId);

        // A profile that still lacks something needs no PDF: the application cannot go ahead.
        if ($this->generatedCvCompleteness($profile)->missing !== []) {
            return null;
        }

        $generated = $this->cvs->execute($userId, $cv);

        return new ApplicantResume($generated->name, $generated->contents);
    }

    public function forApplicationWithResume(int $userId, ?ApplicantResume $resume): ApplicantSubmission
    {
        $profile = $this->profiles->find($userId) ?? JobSeekerProfile::empty($userId);
        $missing = $this->generatedCvCompleteness($profile)->missing;

        return new ApplicantSubmission($missing, $missing === [] ? $resume : null, $this->snapshot($profile));
    }

    /** The completeness for an application that attaches a generated CV: the CV stands for the resume, so one counts. */
    private function generatedCvCompleteness(JobSeekerProfile $profile): ProfileCompleteness
    {
        $counts = $this->profiles->counts($profile->userId);

        return ProfileCompleteness::evaluate($profile, $counts['experiences'], $counts['educations'], 1);
    }

    public function lock(int $userId): void
    {
        $this->profiles->lock($userId, $this->clock->now());
    }

    private function resume(int $userId, int $resumeId): ?ApplicantResume
    {
        $resume = $this->resumes->find($userId, $resumeId);
        $contents = $resume === null ? null : $this->files->get($resume->path);

        return $resume === null || $contents === null ? null : new ApplicantResume($resume->name, $contents);
    }

    /** @return array<string, mixed> */
    private function snapshot(JobSeekerProfile $profile): array
    {
        $userId = $profile->userId;
        $experiences = $this->experiences->forUser($userId);
        $educations = $this->educations->forUser($userId);
        $skillIds = $this->lists->skillIds($userId);
        $languages = $this->lists->languages($userId);

        $names = [
            'salary-periods' => $this->names('salary-periods', [$profile->expectedSalaryPeriodId]),
            'experience-levels' => $this->names('experience-levels', [$profile->experienceLevelId]),
            'job-types' => $this->names('job-types', array_map(static fn (WorkExperience $entry): ?int => $entry->jobTypeId, $experiences)),
            'education-levels' => $this->names('education-levels', array_map(static fn (Education $entry): int => $entry->educationLevelId, $educations)),
            'skills' => $this->names('skills', $skillIds),
            'languages' => $this->names('languages', array_map(static fn ($entry): int => $entry->languageId, $languages)),
        ];

        $location = $this->locations->names($profile->provinceId, $profile->cityId, $profile->subdistrictId, $profile->villageId, $profile->postalCodeId);

        return [
            'version' => self::SNAPSHOT_VERSION,
            'phone' => $profile->phone,
            'whatsapp' => $profile->whatsapp,
            'age' => $profile->birthDate === null ? null : $profile->birthDate->diff($this->today->date())->y,
            'headline' => $profile->headline,
            'summary' => $profile->summary,
            'location' => [
                'province' => $location->province,
                'city' => $location->city,
                'subdistrict' => $location->subdistrict,
                'village' => $location->village,
                'postal_code' => $location->postalCode,
                'address_line' => $profile->addressLine,
            ],
            'expected_salary' => $profile->expectedSalary === null ? null : [
                'amount' => $profile->expectedSalary,
                'period' => $this->named($names['salary-periods'], $profile->expectedSalaryPeriodId),
            ],
            'experience_level' => $this->named($names['experience-levels'], $profile->experienceLevelId),
            'availability' => $profile->availability?->value,
            'available_from' => $profile->availableFrom?->format('Y-m-d'),
            'experiences' => array_map(fn (WorkExperience $entry): array => [
                'job_title' => $entry->jobTitle,
                'company_name' => $entry->companyName,
                'job_type' => $this->named($names['job-types'], $entry->jobTypeId),
                'location_text' => $entry->locationText,
                'start' => $entry->startDate->format('Y-m'),
                'end' => $entry->endDate?->format('Y-m'),
                'is_current' => $entry->isCurrent,
                'description' => $entry->description,
            ], $experiences),
            'educations' => array_map(fn (Education $entry): array => [
                'level' => $this->named($names['education-levels'], $entry->educationLevelId),
                'institution' => $entry->institution,
                'field_of_study' => $entry->fieldOfStudy,
                'start_year' => $entry->startYear,
                'end_year' => $entry->endYear,
                'is_current' => $entry->isCurrent,
                'gpa' => $entry->gpa,
                'description' => $entry->description,
            ], $educations),
            'skills' => array_map(fn (int $id): array => $this->named($names['skills'], $id) ?? ['id' => $id, 'name' => null], $skillIds),
            'languages' => array_map(fn ($entry): array => [
                ...($this->named($names['languages'], $entry->languageId) ?? ['id' => $entry->languageId, 'name' => null]),
                'proficiency' => $entry->proficiency->value,
            ], $languages),
            'links' => array_map(static fn ($link): array => ['type' => $link->type->value, 'url' => $link->url], $this->lists->links($userId)),
        ];
    }

    /**
     * The names of master items in both languages, keyed by id, read in one query per language.
     *
     * @param  list<int|null>  $ids
     * @return array<int, array<string, string>> the names by language, by id
     */
    private function names(string $master, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null)));
        $result = [];

        if ($ids === []) {
            return $result;
        }

        foreach (self::LANGUAGES as $language) {
            foreach ($this->references->names($master, $ids, $language) as $id => $name) {
                $result[$id][$language] = $name;
            }
        }

        return $result;
    }

    /**
     * A master value as `{id, name: {id, en}}`, or null when no value was given.
     *
     * @param  array<int, array<string, string>>  $names
     * @return array{id: int, name: array<string, string|null>}|null
     */
    private function named(array $names, ?int $id): ?array
    {
        if ($id === null) {
            return null;
        }

        $name = $names[$id] ?? [];

        return ['id' => $id, 'name' => ['id' => $name['id'] ?? null, 'en' => $name['en'] ?? null]];
    }
}
