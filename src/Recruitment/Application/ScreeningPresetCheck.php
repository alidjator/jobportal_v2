<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\JobQuestionDraft;
use Src\Recruitment\Domain\ScreeningPresetRepository;
use Src\Recruitment\Domain\ScreeningPresetRules;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** What a preset must satisfy before it is saved, for a new preset and a changed one alike: its name, its level hint and its list of questions. */
final readonly class ScreeningPresetCheck
{
    public function __construct(
        private ScreeningPresetRepository $presets,
        private ScreeningItemDrafter $drafter,
        private ReferenceValues $references,
        private Settings $settings,
    ) {}

    /**
     * @param  list<array{question_id: int, required: bool, must_have: bool, ideal: array<string, mixed>|null}>  $items
     * @param  list<int>  $currentQuestionIds  the questions the preset holds now (none for a new preset)
     * @return array{name: string, drafts: list<JobQuestionDraft>}
     *
     * @throws BusinessRuleViolation `invalid-screening-preset` with `errors` on `name`, `job_level_id` or `questions.<n>.<field>`
     * @throws StateConflict `screening-limit-reached`
     */
    public function run(int $companyId, string $name, ?int $jobLevelId, array $items, array $currentQuestionIds, ?int $presetId): array
    {
        $name = ScreeningPresetRules::name($name);

        if ($this->presets->nameTaken($companyId, $name, $presetId)) {
            throw ScreeningPresetRules::invalid('name', 'A preset with this name already exists.');
        }

        if ($jobLevelId !== null && ! $this->references->isActive('job-levels', $jobLevelId)) {
            throw ScreeningPresetRules::invalid('job_level_id', 'This is not an active job level.');
        }

        $drafts = $this->drafter->drafts($companyId, $items, $currentQuestionIds, 'invalid-screening-preset', 'The screening preset is not valid.');
        $newIds = array_map(static fn (JobQuestionDraft $draft): int => $draft->questionId, $drafts);

        // Like a job: a list that adds nothing new is accepted even above a limit that was lowered after it was saved.
        if (count($drafts) > $this->settings->integer(RecruitmentSettings::MAX_SCREENING_QUESTIONS, $companyId) && array_diff($newIds, $currentQuestionIds) !== []) {
            throw new StateConflict('The preset has reached its limit of screening questions.', 'screening-limit-reached');
        }

        return ['name' => $name, 'drafts' => $drafts];
    }
}
