<?php

namespace Src\JobSeeker\Application\Cv;

use DateTimeImmutable;
use Src\JobSeeker\Domain\CvInclusion;
use Src\JobSeeker\Domain\CvTemplate;
use Src\JobSeeker\Domain\Education;
use Src\JobSeeker\Domain\EducationRepository;
use Src\JobSeeker\Domain\ExperienceRepository;
use Src\JobSeeker\Domain\JobSeekerProfile;
use Src\JobSeeker\Domain\ProfileListRepository;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\JobSeeker\Domain\WorkExperience;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;

/**
 * Collects what a CV prints from the latest profile and words it in the language of the CV. It never reads the expected salary, the street address or the
 * WhatsApp number, and the protected details (birth date, gender, religion, marital status, nationality) and the photo only when `$include` lists them.
 */
final readonly class CvDocumentBuilder
{
    private const array IMAGE_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    public function __construct(
        private ProfileRepository $profiles,
        private ExperienceRepository $experiences,
        private EducationRepository $educations,
        private ProfileListRepository $lists,
        private ReferenceValues $references,
        private LocationReferences $locations,
        private AccountPictures $pictures,
        private FileStorage $files,
    ) {}

    /** @param  list<CvInclusion>  $include */
    public function build(int $userId, ApplicantContact $contact, CvTemplate $template, string $language, array $include): CvDocument
    {
        $profile = $this->profiles->find($userId) ?? JobSeekerProfile::empty($userId);
        $words = CvLabels::words($language);
        $experiences = $this->experiences->forUser($userId);
        $educations = $this->educations->forUser($userId);
        $languageEntries = $this->lists->languages($userId);
        $location = $this->locations->names($profile->provinceId, $profile->cityId, null, null, null);

        $skills = $this->names('skills', $this->lists->skillIds($userId), $language);
        $levels = $this->names('education-levels', array_map(static fn (Education $entry): int => $entry->educationLevelId, $educations), $language);
        $spoken = $this->names('languages', array_map(static fn ($entry): int => $entry->languageId, $languageEntries), $language);

        return new CvDocument(
            $template,
            $language,
            $words,
            $contact->name,
            $contact->email,
            $profile->phone,
            $this->join([$location->city, $location->province]),
            $profile->headline,
            $profile->summary,
            in_array(CvInclusion::Photo, $include, true) ? $this->photo($userId) : null,
            $this->personal($profile, $include, $words, $language),
            array_map(fn (WorkExperience $entry): array => [
                'title' => $entry->jobTitle,
                'company' => $entry->companyName,
                'location' => $entry->locationText,
                'period' => $this->period($entry->startDate, $entry->isCurrent ? null : $entry->endDate, $words['present'], $language),
                'description' => $entry->description,
            ], $experiences),
            array_map(fn (Education $entry): array => [
                'title' => $this->join([$levels[$entry->educationLevelId] ?? null, $entry->fieldOfStudy], ' - '),
                'institution' => $entry->institution,
                'period' => $entry->isCurrent
                    ? $entry->startYear.' - '.$words['present']
                    : ($entry->endYear === null || $entry->endYear === $entry->startYear ? (string) $entry->startYear : $entry->startYear.' - '.$entry->endYear),
                'gpa' => $entry->gpa === null ? null : $words['gpa'].' '.$entry->gpa,
                'description' => $entry->description,
            ], $educations),
            array_values(array_filter($skills)),
            array_values(array_filter(array_map(static fn ($entry): ?array => isset($spoken[$entry->languageId])
                ? ['name' => $spoken[$entry->languageId], 'level' => $words['proficiency.'.$entry->proficiency->value]]
                : null, $languageEntries))),
            array_map(static fn ($link): array => ['label' => $words['link.'.$link->type->value], 'url' => $link->url], $this->lists->links($userId)),
        );
    }

    /**
     * @param  list<CvInclusion>  $include
     * @param  array<string, string>  $words
     * @return list<array{label: string, value: string}>
     */
    private function personal(JobSeekerProfile $profile, array $include, array $words, string $language): array
    {
        $rows = [];
        $add = static function (string $key, ?string $value) use (&$rows, $words): void {
            if ($value !== null && $value !== '') {
                $rows[] = ['label' => $words[$key], 'value' => $value];
            }
        };

        foreach (CvInclusion::cases() as $inclusion) {
            if (! in_array($inclusion, $include, true)) {
                continue;
            }

            match ($inclusion) {
                CvInclusion::Photo => null,
                CvInclusion::BirthDate => $add('birth_date', $profile->birthDate === null ? null : CvLabels::day($profile->birthDate, $language)),
                CvInclusion::Gender => $add('gender', $profile->gender === null ? null : $words['gender.'.$profile->gender->value]),
                CvInclusion::Religion => $add('religion', $this->name('religions', $profile->religionId, $language)),
                CvInclusion::MaritalStatus => $add('marital_status', $profile->maritalStatus === null ? null : $words['marital.'.$profile->maritalStatus->value]),
                CvInclusion::Nationality => $add('nationality', $this->name('nationalities', $profile->nationalityId, $language)),
            };
        }

        return $rows;
    }

    private function period(DateTimeImmutable $start, ?DateTimeImmutable $end, string $present, string $language): string
    {
        return CvLabels::month($start, $language).' - '.($end === null ? $present : CvLabels::month($end, $language));
    }

    private function photo(int $userId): ?string
    {
        $path = $this->pictures->pathOf($userId);
        $type = $path === null ? null : (self::IMAGE_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null);
        $contents = $path === null || $type === null ? null : $this->files->get($path);

        return $contents === null ? null : 'data:'.$type.';base64,'.base64_encode($contents);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function names(string $master, array $ids, string $language): array
    {
        $ids = array_values(array_unique($ids));

        return $ids === [] ? [] : $this->references->names($master, $ids, $language);
    }

    private function name(string $master, ?int $id, string $language): ?string
    {
        return $id === null ? null : $this->references->name($master, $id, $language);
    }

    /** @param  list<string|null>  $parts */
    private function join(array $parts, string $glue = ', '): ?string
    {
        $parts = array_values(array_filter($parts, static fn (?string $part): bool => $part !== null && $part !== ''));

        return $parts === [] ? null : implode($glue, $parts);
    }
}
