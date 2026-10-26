<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Candidate\CandidateRef;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One application for a member who may see its job (see `CompanyApplicationAccess`) or sits on one of its interview panels (reduced: no ratings, no other applications); a group member reading it leaves it unmarked as viewed. The contact data is closed for an unverified company while the application is in `applied` (`ContactVisibility`). */
final readonly class GetCompanyApplication
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private RecruiterApplicationReader $reader,
        private StageRepository $stages,
        private JobDirectory $jobs,
        private CompanyMemberAccounts $accounts,
        private ApplicationRatings $ratings,
        private ApplicationInterviewResults $interviewResults,
        private ApplicationRepository $applications,
        private ContactVisibility $contact,
        private OtherApplications $others,
        private ApplicationReviews $reviews,
        private Settings $settings,
        private Clock $clock,
        private ApplicantContacts $contacts,
        private CandidateDirectory $candidates,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): CompanyApplicationView
    {
        $team = $this->lookup->find($actor, $id, panelOpens: true);
        $member = $team->member;
        $data = $this->reader->find($id) ?? throw EntityNotFound::of('Application', $id);

        // Someone who sits on the panel and does not see the job leaves no trace of having viewed it and sees no ratings and no other applications.
        if (! $actor->groupRead && ! $team->panelOnly) {
            $this->applications->markViewed($id, $this->clock->now());
        }

        if ($this->contact->isClosed($data->companyId, $data->stageType)) {
            $data = $data->withContactHidden();
        }

        $stageNames = [];

        foreach ($this->stages->forCompany($member->companyId) as $stage) {
            $stageNames[(int) $stage->id] = $stage->name;
        }

        $actorIds = [];

        foreach ($data->history as $entry) {
            if ($entry->by === StageMoveActor::Recruiter && $entry->actorId !== null) {
                $actorIds[$entry->actorId] = $entry->actorId;
            }
        }

        $actorNames = [];

        foreach ($this->accounts->summaries(array_values($actorIds)) as $userId => $summary) {
            $actorNames[$userId] = $summary->name;
        }

        [$otherApplications, $othersHidden] = $team->panelOnly ? [[], 0] : $this->others->of($member, $data);
        $untouched = UntouchedImport::applies($data->migratedAt, $data->stageEnteredAt);
        $days = StageClock::days($data->stageEnteredAt, $this->clock->now());
        $candidate = $team->panelOnly ? null : $this->candidate($data->userId);

        return new CompanyApplicationView(
            $data,
            $this->jobs->titles([$data->jobId])[$data->jobId] ?? null,
            $stageNames[$data->stageId] ?? $data->stageType->defaultName(),
            $stageNames,
            $actorNames,
            $team->panelOnly ? new ApplicationRatingView(null, 0, null, []) : $this->ratings->view($id, $member->userId),
            $this->interviewResults->forApplication($id, $member->userId),
            $untouched ? null : $days,
            ! $untouched && StageClock::isStale($data->stageType, $days, $this->settings->integer(RecruitmentSettings::STALE_AFTER_DAYS, $data->companyId)),
            $otherApplications,
            $othersHidden,
            $team->panelOnly ? [] : $this->reviews->forApplication($id, $member->userId),
            $this->contacts->undeliverable([$data->userId]) === [],
            $candidate?->slug,
            $candidate->hasAvatar ?? false,
        );
    }

    /** The applicant's profile in the candidate search, only while they can be found right now (opted in, active, complete profile); null otherwise. */
    private function candidate(int $userId): ?CandidateRef
    {
        if ($this->candidates->findableIds([$userId]) === []) {
            return null;
        }

        return $this->candidates->refs([$userId])[$userId] ?? null;
    }
}
