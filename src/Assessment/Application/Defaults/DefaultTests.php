<?php

namespace Src\Assessment\Application\Defaults;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Application\Delivery\InvitationChecks;
use Src\Assessment\Domain\JobStageTest;
use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMessageTexts;
use Src\Shared\Application\Recruitment\CompanyStage;
use Src\Shared\Application\Recruitment\CompanyStages;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ErrorMessage;

/**
 * The rules of a default test (`docs/design/phase-8-assessment-automation.md` sections 1 and 2) and the invitation it makes when an application moves into its stage. The test, the
 * assessors and the window are judged by the same checks as a test sent by hand; what is added is the stage, the days to complete it and the advance rule.
 */
final readonly class DefaultTests
{
    /** The extra minutes on the shortest window, so a clock that has moved on a little by the time the invitation is checked does not make it too short. */
    private const int MARGIN_MINUTES = 1;

    public function __construct(
        private CompanyStages $stages,
        private TestRepository $tests,
        private SectionRepository $sections,
        private InvitationChecks $checks,
        private CompanyMessageTexts $texts,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /**
     * @throws BusinessRuleViolation `stage-not-assessment`, `invalid-assessment-schedule`, `invalid-assessment-test`, `invalid-assessor`, `advance-not-allowed`, `invalid-advance-stage` or `message-link-not-allowed`
     */
    public function assertValid(int $companyId, JobStageTest $default): void
    {
        $stages = $this->stages->ofCompany($companyId);
        $stage = $stages[$default->stageId] ?? null;

        if (! $stage instanceof CompanyStage || $stage->type !== 'assessment') {
            $this->refuse('stage-not-assessment', 'stage_id', 'Choose an assessment stage of your company.');
        }

        $maxDays = $this->settings->integer(AssessmentSettings::MAX_DUE_DAYS, $companyId);

        if ($default->dueDays < 1 || $default->dueDays > $maxDays) {
            $this->refuse('invalid-assessment-schedule', 'due_days', new ErrorMessage('The days to complete the test must be from 1 to :days.', ['days' => $maxDays]));
        }

        $this->checks->check($companyId, $default->jobId, $this->inputFor($companyId, $default));

        if ($default->advanceToStageId === null) {
            return;
        }

        $test = $this->tests->find($default->testId);

        if (! $test instanceof Test || $test->kind() === TestKind::Psychological) {
            $this->refuse('advance-not-allowed', 'advance', 'Only a skill test can move an application on by its score.');
        }

        $target = $stages[$default->advanceToStageId] ?? null;

        if (! $target instanceof CompanyStage || $target->id === $default->stageId || ! in_array($target->type, ['assessment', 'interview'], true)) {
            $this->refuse('invalid-advance-stage', 'advance.to_stage_id', 'Choose an assessment stage (another one) or an interview stage of your company.');
        }
    }

    /**
     * The invitation this default sends now: it starts at once and is due `dueDays` days from now, or later when the company's shortest window or the test's duration needs more time.
     *
     * @throws BusinessRuleViolation when the test is not usable
     */
    public function inputFor(int $companyId, JobStageTest $default): AssessmentInvitationInput
    {
        $now = $this->clock->now();
        $minHours = $this->settings->integer(AssessmentSettings::MIN_DUE_HOURS, $companyId);
        $seconds = array_sum(array_map(static fn ($section): int => $section->durationSeconds, $this->sections->ofTest($default->testId)));

        $due = max(
            $now->modify("+{$default->dueDays} days"),
            $now->modify("+{$minHours} hours +".self::MARGIN_MINUTES.' minutes'),
            $now->modify('+'.max(1, $seconds).' seconds +'.self::MARGIN_MINUTES.' minutes'),
        );

        return new AssessmentInvitationInput($default->testId, null, $due, $default->assessorIds, note: $this->texts->assessment($companyId));
    }

    private function refuse(string $code, string $field, string|ErrorMessage $message): never
    {
        throw new BusinessRuleViolation($message, $code, extensions: ['errors' => [$field => [$message]]]);
    }
}
