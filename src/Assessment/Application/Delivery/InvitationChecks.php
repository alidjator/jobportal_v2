<?php

namespace Src\Assessment\Application\Delivery;

use DateTimeImmutable;
use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\InvitationMode;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The rules of sending a test: the test is published and the company may use it, the window leaves room for the test and stays within the company's limits,
 * and the assessors are members who work on the job. The errors name the request fields (`test_id`, `starts_at`, `due_at`, `assessor_ids`).
 */
final readonly class InvitationChecks
{
    public const int MAX_ASSESSORS = 5;

    /** A start time a few minutes in the past is accepted, so a client that fills in "now" is not refused by its own latency. */
    private const int START_GRACE_MINUTES = 5;

    public function __construct(
        private TestRepository $tests,
        private SectionRepository $sections,
        private QuestionRepository $questions,
        private CompanyTeam $team,
        private JobDirectory $jobs,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /**
     * @throws BusinessRuleViolation `invalid-assessment-test`, `invalid-assessment-schedule`, `invalid-assessor` or `invalid-assessment-input`
     */
    public function check(int $companyId, int $jobId, AssessmentInvitationInput $input): CheckedInvitation
    {
        $test = $this->usableTest($companyId, $input->testId);
        $now = $this->clock->now();
        $startsAt = $input->startsAt ?? $now;

        $this->assertWindow($companyId, $test, $startsAt, $input->dueAt, $now);
        $assessors = array_values(array_unique($input->assessorIds));
        $this->assertAssessors($companyId, $jobId, $test, $assessors);

        $mode = InvitationMode::tryFrom($input->mode) ?? InvitationMode::Online;

        if ($mode === InvitationMode::Onsite && $input->location === null) {
            $this->refuse('invalid-assessment-input', 'location', 'Name the place where the test is taken: an onsite test needs a location.');
        }

        return new CheckedInvitation($test, $startsAt, $input->dueAt, $assessors, $mode, $mode === InvitationMode::Onsite ? $input->location : null);
    }

    /** A published test of the company, of the library, or a granted battery of an active provider when the company may use batteries. */
    private function usableTest(int $companyId, int $testId): Test
    {
        $test = $this->tests->find($testId);

        if (! $test instanceof Test || ! $this->mayUse($companyId, $test)) {
            $this->refuse('invalid-assessment-test', 'test_id', 'Choose a published test that your company may use.');
        }

        return $test;
    }

    private function mayUse(int $companyId, Test $test): bool
    {
        if (! $test->isPublished()) {
            return false;
        }

        return match (true) {
            $test->owner->companyId !== null => $test->owner->companyId === $companyId,
            $test->owner->isLibrary() => true,
            default => $this->settings->boolean(AssessmentSettings::PSYCHOLOGICAL_ENABLED, $companyId) && $this->tests->batteryIsUsableBy($companyId, (int) $test->id),
        };
    }

    private function assertWindow(int $companyId, Test $test, DateTimeImmutable $startsAt, DateTimeImmutable $dueAt, DateTimeImmutable $now): void
    {
        if ($startsAt < $now->modify('-'.self::START_GRACE_MINUTES.' minutes')) {
            $this->refuse('invalid-assessment-schedule', 'starts_at', 'The start time cannot be in the past.');
        }

        $minHours = $this->settings->integer(AssessmentSettings::MIN_DUE_HOURS, $companyId);
        $maxDays = $this->settings->integer(AssessmentSettings::MAX_DUE_DAYS, $companyId);

        if ($dueAt < $now->modify("+{$minHours} hours")) {
            $this->refuse('invalid-assessment-schedule', 'due_at', "The due time must be at least {$minHours} hour".($minHours === 1 ? '' : 's').' from now.');
        }

        if ($dueAt > $now->modify("+{$maxDays} days")) {
            $this->refuse('invalid-assessment-schedule', 'due_at', "The due time can be at most {$maxDays} day".($maxDays === 1 ? '' : 's').' from now.');
        }

        $seconds = array_sum(array_map(static fn ($section): int => $section->durationSeconds, $this->sections->ofTest((int) $test->id)));

        if ($dueAt < $startsAt->modify('+'.max(1, $seconds).' seconds')) {
            $this->refuse('invalid-assessment-schedule', 'due_at', 'The due time must leave enough time to take the test, which lasts '.intdiv($seconds + 59, 60).' minutes, after the start time.');
        }
    }

    /**
     * A skill test with long-text or video answers needs at least one assessor; a battery is scored by its psychologist, so it has none.
     *
     * @param  list<int>  $assessors
     */
    private function assertAssessors(int $companyId, int $jobId, Test $test, array $assessors): void
    {
        if ($test->kind() === TestKind::Psychological) {
            if ($assessors !== []) {
                $this->refuse('invalid-assessor', 'assessor_ids', 'A psychological battery is scored by its psychologist, so leave the assessors empty.');
            }

            return;
        }

        if (count($assessors) > self::MAX_ASSESSORS) {
            $this->refuse('invalid-assessor', 'assessor_ids', 'Choose at most '.self::MAX_ASSESSORS.' assessors.');
        }

        if ($assessors === [] && $this->questions->testHasType((int) $test->id, QuestionType::LongText)) {
            $this->refuse('invalid-assessor', 'assessor_ids', 'Choose at least one assessor: this test has long-text answers to score.');
        }

        if ($assessors === [] && $this->questions->testHasType((int) $test->id, QuestionType::VideoAnswer)) {
            $this->refuse('invalid-assessor', 'assessor_ids', 'Choose at least one assessor: this test has video answers to score.');
        }

        $roles = $assessors === [] ? [] : $this->team->roles($companyId);
        $viewAll = $assessors === [] ? [] : $this->team->viewAllUserIds($companyId);

        foreach ($assessors as $userId) {
            if (! array_key_exists($userId, $roles) || $this->jobs->visibleToMember($jobId, $companyId, $userId, in_array($userId, $viewAll, true)) === null) {
                $this->refuse('invalid-assessor', 'assessor_ids', 'Choose members of your team who work on this job.');
            }
        }
    }

    private function refuse(string $code, string $field, string $message): never
    {
        throw new BusinessRuleViolation($message, $code, extensions: ['errors' => [$field => [$message]]]);
    }
}
