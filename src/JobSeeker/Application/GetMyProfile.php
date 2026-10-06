<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\EducationRepository;
use Src\JobSeeker\Domain\ExperienceRepository;
use Src\JobSeeker\Domain\JobSeekerProfile;
use Src\JobSeeker\Domain\ProfileCompleteness;
use Src\JobSeeker\Domain\ProfileListRepository;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The caller's own profile with its lists. An account that never saved one gets the empty profile, so the client always has something to show. */
final readonly class GetMyProfile
{
    public function __construct(
        private JobSeekerGuard $guard,
        private ProfileRepository $profiles,
        private ExperienceRepository $experiences,
        private EducationRepository $educations,
        private ProfileListRepository $lists,
        private ResumeRepository $resumes,
        private ReferenceValues $references,
    ) {}

    /** @throws AccessDenied `job-seeker-only` */
    public function execute(Actor $actor, string $language): MyProfile
    {
        $userId = $this->guard->userId($actor);
        $profile = $this->profiles->find($userId) ?? JobSeekerProfile::empty($userId);
        $counts = $this->profiles->counts($userId);

        $skillIds = $this->lists->skillIds($userId);
        $skillNames = $this->references->names('skills', $skillIds, $language);
        $languages = $this->lists->languages($userId);
        $languageNames = $this->references->names('languages', array_map(static fn ($entry): int => $entry->languageId, $languages), $language);

        return new MyProfile(
            $profile,
            ProfileCompleteness::evaluate($profile, $counts['experiences'], $counts['educations'], $counts['resumes']),
            $this->experiences->forUser($userId),
            $this->educations->forUser($userId),
            array_map(static fn (int $id): array => ['id' => $id, 'name' => $skillNames[$id] ?? null], $skillIds),
            array_map(static fn ($entry): array => ['id' => $entry->languageId, 'name' => $languageNames[$entry->languageId] ?? null, 'proficiency' => $entry->proficiency], $languages),
            $this->lists->links($userId),
            $this->resumes->forUser($userId),
        );
    }
}
