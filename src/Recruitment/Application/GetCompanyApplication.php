<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One application for a member who may see its job (see `CompanyApplicationAccess`). The contact data is closed for an unverified company while the application is in `applied` (`ContactVisibility`). */
final readonly class GetCompanyApplication
{
    public function __construct(
        private RecruitmentAccess $access,
        private CompanyApplicationAccess $applicationAccess,
        private RecruiterApplicationReader $reader,
        private StageRepository $stages,
        private JobDirectory $jobs,
        private CompanyMemberAccounts $accounts,
        private ApplicationRatings $ratings,
        private ApplicationInterviewResults $interviewResults,
        private ApplicationRepository $applications,
        private ContactVisibility $contact,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): CompanyApplicationView
    {
        $member = $this->access->viewer($actor);
        $data = $this->reader->find($id) ?? throw EntityNotFound::of('Application', $id);
        $this->applicationAccess->assertCanSee($member, $id, $data->companyId, $data->jobId);
        $this->applications->markViewed($id, $this->clock->now());

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

        return new CompanyApplicationView(
            $data,
            $this->jobs->titles([$data->jobId])[$data->jobId] ?? null,
            $stageNames[$data->stageId] ?? $data->stageType->defaultName(),
            $stageNames,
            $actorNames,
            $this->ratings->view($id, $member->userId),
            $this->interviewResults->forApplication($id, $member->userId),
        );
    }
}
