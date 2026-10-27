<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\JobQuestion;
use Src\Recruitment\Domain\ScreeningPresetRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A member who may manage the job copies a preset into it: the job's whole list becomes the preset's content, exactly as if the member had sent that list
 * to `ReplaceJobScreeningQuestions` (same access, locks and limit). Nothing points back to the preset afterwards. A preset that holds an archived question
 * is refused, because an archived question cannot be attached anew.
 */
final readonly class ApplyScreeningPreset
{
    public function __construct(
        private RecruitmentAccess $access,
        private ScreeningSwitch $switch,
        private ScreeningPresetRepository $presets,
        private ReplaceJobScreeningQuestions $replace,
    ) {}

    /**
     * @return list<JobQuestion> the new list of the job
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `screening-disabled`
     * @throws EntityNotFound when the job or the preset does not exist in the company, or the member may not see the job
     * @throws StateConflict `job-not-editable`, `job-questions-locked` or `screening-limit-reached`
     * @throws BusinessRuleViolation `invalid-job-questions` with `errors` on `questions.<n>.question_id` for each archived question
     */
    public function execute(Actor $actor, int $jobId, int $presetId): array
    {
        $member = $this->access->viewer($actor, CompanyPermission::JobsCreate);
        $this->switch->assertOn($member->companyId);
        $this->access->job($member, $jobId);

        $preset = $this->presets->find($presetId);

        if ($preset === null || $preset->companyId !== $member->companyId) {
            throw EntityNotFound::of('Screening preset', $presetId);
        }

        $errors = [];

        foreach ($preset->questions as $index => $item) {
            if ($item->question->isArchived()) {
                $errors["questions.{$index}.question_id"][] = 'This question is archived. Restore it or remove it from the preset first.';
            }
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('The questions of the job are not valid.', 'invalid-job-questions', extensions: ['errors' => $errors]);
        }

        return $this->replace->execute($actor, $jobId, array_map(static fn (JobQuestion $item): array => [
            'question_id' => $item->question->id,
            'required' => $item->isRequired,
            'must_have' => $item->isMustHave,
            'ideal' => $item->ideal?->toInput(),
        ], $preset->questions));
    }
}
