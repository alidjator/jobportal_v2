<?php

namespace Src\JobSeeker\Application\Export;

use DateTimeInterface;
use Src\JobSeeker\Domain\EducationRepository;
use Src\JobSeeker\Domain\ExperienceRepository;
use Src\JobSeeker\Domain\JobSeekerProfile;
use Src\JobSeeker\Domain\ProfileListRepository;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\JobSeeker\Domain\ProfileViewRepository;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Privacy\ExportedFile;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;

/**
 * The JobSeeker part of the personal data export: the profile with its experiences, education, skills, languages and links, the resumes (their names and files)
 * and the companies that opened the profile. Values of a master are given with their Indonesian name; places by name.
 */
final readonly class ProfileDataExporter implements PersonalDataExporter
{
    private const string LANGUAGE = 'id';

    public function __construct(
        private ProfileRepository $profiles,
        private ExperienceRepository $experiences,
        private EducationRepository $educations,
        private ProfileListRepository $lists,
        private ResumeRepository $resumes,
        private ProfileViewRepository $views,
        private ReferenceValues $references,
        private LocationReferences $locations,
        private CompanyDirectory $companies,
    ) {}

    public function key(): string
    {
        return 'profile';
    }

    public function export(int $userId): PersonalDataSection
    {
        $resumes = $this->resumes->forUser($userId);
        $files = [];
        $resumeRows = [];

        foreach ($resumes as $resume) {
            $archiveName = 'resume-'.$resume->id.'.pdf';
            $files[] = new ExportedFile($resume->path, $archiveName);
            $resumeRows[] = ['name' => $resume->name, 'size_bytes' => $resume->sizeBytes, 'uploaded_at' => $resume->createdAt->format(DateTimeInterface::ATOM), 'file' => 'files/profile/'.$archiveName];
        }

        return new PersonalDataSection([
            'details' => $this->details($this->profiles->find($userId) ?? JobSeekerProfile::empty($userId)),
            'experiences' => array_map(fn ($entry): array => [
                'job_title' => $entry->jobTitle,
                'company_name' => $entry->companyName,
                'job_type' => $this->name('job-types', $entry->jobTypeId),
                'location' => $entry->locationText,
                'start_date' => $entry->startDate->format('Y-m-d'),
                'end_date' => $entry->endDate?->format('Y-m-d'),
                'is_current' => $entry->isCurrent,
                'description' => $entry->description,
            ], $this->experiences->forUser($userId)),
            'educations' => array_map(fn ($entry): array => [
                'education_level' => $this->name('education-levels', $entry->educationLevelId),
                'institution' => $entry->institution,
                'field_of_study' => $entry->fieldOfStudy,
                'start_year' => $entry->startYear,
                'end_year' => $entry->endYear,
                'is_current' => $entry->isCurrent,
                'gpa' => $entry->gpa,
                'description' => $entry->description,
            ], $this->educations->forUser($userId)),
            'skills' => array_values($this->references->names('skills', $this->lists->skillIds($userId), self::LANGUAGE)),
            'languages' => $this->languages($userId),
            'links' => array_map(static fn ($link): array => ['type' => $link->type->value, 'url' => $link->url], $this->lists->links($userId)),
            'resumes' => $resumeRows,
            'viewed_by_companies' => $this->viewers($userId),
        ], $files);
    }

    /** @return array<string, mixed> */
    private function details(JobSeekerProfile $profile): array
    {
        $location = $this->locations->names($profile->provinceId, $profile->cityId, $profile->subdistrictId, $profile->villageId, $profile->postalCodeId);

        return [
            'phone' => $profile->phone,
            'whatsapp' => $profile->whatsapp,
            'birth_date' => $profile->birthDate?->format('Y-m-d'),
            'gender' => $profile->gender?->value,
            'marital_status' => $profile->maritalStatus?->value,
            'religion' => $this->name('religions', $profile->religionId),
            'nationality' => $this->name('nationalities', $profile->nationalityId),
            'expected_salary' => $profile->expectedSalary,
            'expected_salary_period' => $this->name('salary-periods', $profile->expectedSalaryPeriodId),
            'experience_level' => $this->name('experience-levels', $profile->experienceLevelId),
            'availability' => $profile->availability?->value,
            'available_from' => $profile->availableFrom?->format('Y-m-d'),
            'province' => $location->province,
            'city' => $location->city,
            'subdistrict' => $location->subdistrict,
            'village' => $location->village,
            'postal_code' => $location->postalCode,
            'address_line' => $profile->addressLine,
            'headline' => $profile->headline,
            'summary' => $profile->summary,
            'visibility' => ['profile_discoverable_by_companies' => $profile->isDiscoverable, 'resume_discoverable_by_companies' => $profile->resumeDiscoverable],
        ];
    }

    /** @return list<array{language: string|null, proficiency: string}> */
    private function languages(int $userId): array
    {
        $entries = $this->lists->languages($userId);
        $names = $this->references->names('languages', array_map(static fn ($entry): int => $entry->languageId, $entries), self::LANGUAGE);

        return array_map(static fn ($entry): array => ['language' => $names[$entry->languageId] ?? null, 'proficiency' => $entry->proficiency->value], $entries);
    }

    /** @return list<array{company: string|null, kind: string, day: string}> */
    private function viewers(int $userId): array
    {
        $views = [];

        for ($page = 1; ; $page++) {
            $result = $this->views->ofCandidate($userId, new PageRequest($page, PageRequest::MAX_PER_PAGE));
            array_push($views, ...$result->items);

            if (count($result->items) < PageRequest::MAX_PER_PAGE) {
                break;
            }
        }

        $companies = $this->companies->summaries(array_values(array_unique(array_map(static fn (array $view): int => $view['company_id'], $views))));

        return array_map(static fn (array $view): array => ['company' => ($companies[$view['company_id']] ?? null)?->name, 'kind' => $view['kind']->value, 'day' => $view['day']], $views);
    }

    private function name(string $master, ?int $id): ?string
    {
        return $id === null ? null : $this->references->name($master, $id, self::LANGUAGE);
    }
}
