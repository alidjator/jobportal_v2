<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\JobQuestion;
use Src\Recruitment\Domain\JobQuestionDraft;
use Src\Recruitment\Domain\JobQuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A member who may manage the job sets its whole ordered list of screening questions. Only while the job can be edited and has no applications.
 * A must-have question is always required and needs an ideal answer; the other questions have none. A question of the library can be attached when it
 * is the company's and not archived (a job that already has an archived question keeps it). More questions than the company's limit are refused, except
 * that a list that adds nothing new is always accepted.
 */
final readonly class ReplaceJobScreeningQuestions
{
    public function __construct(
        private RecruitmentAccess $access,
        private ScreeningSwitch $switch,
        private JobDirectory $jobs,
        private JobApplicationCheck $applications,
        private JobQuestionRepository $jobQuestions,
        private ScreeningItemDrafter $drafter,
        private Settings $settings,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  list<array{question_id: int, required: bool, must_have: bool, ideal: array<string, mixed>|null}>  $items
     * @return list<JobQuestion>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `screening-disabled`
     * @throws EntityNotFound when the job does not exist in the company or the member may not see it
     * @throws StateConflict `job-not-editable`, `job-questions-locked` or `screening-limit-reached`
     * @throws BusinessRuleViolation `invalid-job-questions` with `errors` per `questions.<n>.<field>`
     */
    public function execute(Actor $actor, int $jobId, array $items): array
    {
        $member = $this->access->viewer($actor, CompanyPermission::JobsCreate);
        $this->switch->assertOn($member->companyId);
        $this->access->job($member, $jobId);
        $now = $this->clock->now();

        $this->transactions->run(function () use ($member, $jobId, $items, $now): void {
            $this->jobs->lock($jobId);
            $job = $this->access->job($member, $jobId);

            if (! $job->isEditable) {
                throw new StateConflict('This job cannot be changed in its current status.', 'job-not-editable');
            }

            if ($this->applications->hasApplications($jobId)) {
                throw new StateConflict('The job has applications, so its questions can no longer change.', 'job-questions-locked');
            }

            $currentIds = array_map(static fn (JobQuestion $current): int => $current->question->id, $this->jobQuestions->forJob($jobId));
            $drafts = $this->drafter->drafts($member->companyId, $items, $currentIds, 'invalid-job-questions', 'The questions of the job are not valid.');

            $newIds = array_map(static fn (JobQuestionDraft $draft): int => $draft->questionId, $drafts);

            if (count($drafts) > $this->settings->integer(RecruitmentSettings::MAX_SCREENING_QUESTIONS, $member->companyId) && array_diff($newIds, $currentIds) !== []) {
                throw new StateConflict('The job has reached its limit of screening questions.', 'screening-limit-reached');
            }

            $this->jobQuestions->replaceForJob($jobId, $drafts, $now);
        });

        return $this->jobQuestions->forJob($jobId);
    }
}
