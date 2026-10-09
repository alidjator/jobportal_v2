<?php

namespace Src\JobSeeker\Application\Candidates;

use Src\JobSeeker\Domain\Education;
use Src\JobSeeker\Domain\EducationRepository;
use Src\JobSeeker\Domain\ExperienceRepository;
use Src\JobSeeker\Domain\JobSeekerProfile;
use Src\JobSeeker\Domain\ProfileListRepository;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\JobSeeker\Domain\WorkExperience;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Opens a candidate's profile for a company member and logs it. The contact data (email, phone, WhatsApp, street address and links) is part of the answer only
 * when the member already sees an application of the candidate (see `ApplicantRelations`); the resumes are listed only when the candidate lets companies see them.
 */
final readonly class GetCandidate
{
    public function __construct(
        private CandidateAccess $access,
        private CandidateReader $candidates,
        private ProfileRepository $profiles,
        private ExperienceRepository $experiences,
        private EducationRepository $educations,
        private ProfileListRepository $lists,
        private ResumeRepository $resumes,
        private CandidateLabels $labels,
        private ApplicantRelations $relations,
        private ApplicantContacts $contacts,
        private ProfileViews $views,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     * @throws TooManyAttempts `candidate-view-limit`
     */
    public function execute(Actor $actor, string $slug, string $language): CandidateProfileView
    {
        $viewer = $this->access->viewer($actor);
        $row = $this->candidates->findBySlug($slug) ?? throw EntityNotFound::of('Candidate', $slug);
        $userId = $row->userId;
        $profile = $this->profiles->find($userId) ?? JobSeekerProfile::empty($userId);

        $this->views->profileOpened($viewer, $userId);

        $experiences = $this->experiences->forUser($userId);
        $educations = $this->educations->forUser($userId);
        $skillIds = $this->lists->skillIds($userId);
        $languages = $this->lists->languages($userId);

        $jobTypes = $this->labels->names('job-types', array_map(static fn (WorkExperience $entry): ?int => $entry->jobTypeId, $experiences), $language);
        $levels = $this->labels->names('education-levels', array_map(static fn (Education $entry): int => $entry->educationLevelId, $educations), $language);
        $skills = $this->labels->names('skills', $skillIds, $language);
        $languageNames = $this->labels->names('languages', array_map(static fn ($entry): int => $entry->languageId, $languages), $language);
        $periods = $this->labels->names('salary-periods', [$profile->expectedSalaryPeriodId], $language);
        $experienceLevels = $this->labels->names('experience-levels', [$profile->experienceLevelId], $language);
        $provinces = $this->labels->provinces([$profile->provinceId]);
        $cities = $this->labels->cities([$profile->cityId]);

        return new CandidateProfileView(
            $row->slug,
            $row->name,
            $row->avatarPath !== null,
            $profile,
            CandidateLabels::named($provinces, $profile->provinceId),
            CandidateLabels::named($cities, $profile->cityId),
            CandidateLabels::named($experienceLevels, $profile->experienceLevelId),
            $profile->expectedSalary === null ? null : ['amount' => $profile->expectedSalary, 'period' => CandidateLabels::named($periods, $profile->expectedSalaryPeriodId)],
            array_map(static fn (WorkExperience $entry): array => ['entry' => $entry, 'job_type' => CandidateLabels::named($jobTypes, $entry->jobTypeId)], $experiences),
            array_map(static fn (Education $entry): array => ['entry' => $entry, 'level' => CandidateLabels::named($levels, $entry->educationLevelId) ?? ['id' => $entry->educationLevelId, 'name' => null]], $educations),
            array_map(static fn (int $id): array => ['id' => $id, 'name' => $skills[$id] ?? null], $skillIds),
            array_map(static fn ($entry): array => ['id' => $entry->languageId, 'name' => $languageNames[$entry->languageId] ?? null, 'proficiency' => $entry->proficiency->value], $languages),
            $profile->resumeDiscoverable ? $this->resumes->forUser($userId) : [],
            $this->relations->canSeeApplicant($viewer->companyId, $viewer->memberId, $viewer->isCompanyAdmin, $userId) ? $this->contact($profile) : null,
            $row->updatedAt,
        );
    }

    /** @return array{email: string|null, phone: string|null, whatsapp: string|null, address_line: string|null, links: list<array{type: string, url: string}>} */
    private function contact(JobSeekerProfile $profile): array
    {
        return [
            'email' => $this->contacts->contact($profile->userId)?->email,
            'phone' => $profile->phone,
            'whatsapp' => $profile->whatsapp,
            'address_line' => $profile->addressLine,
            'links' => array_map(static fn ($link): array => ['type' => $link->type->value, 'url' => $link->url], $this->lists->links($profile->userId)),
        ];
    }
}
