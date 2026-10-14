<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\QuestionRules;
use Src\Recruitment\Domain\ScreeningQuestion;
use Src\Recruitment\Domain\ScreeningQuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin changes the text and/or the choices of a question; the type never changes. Refused while applications or answers depend on the
 * question, and when a choice that a job uses as its ideal answer would disappear.
 */
final readonly class UpdateScreeningQuestion
{
    public function __construct(
        private RecruitmentAccess $access,
        private ScreeningSwitch $switch,
        private ScreeningQuestionRepository $questions,
        private ScreeningQuestionUsage $usage,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  list<array{id: int|null, label: string}>|null  $options  the whole new list of choices, or null to leave them alone
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `screening-disabled`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-screening-question`
     * @throws StateConflict `question-in-use`
     */
    public function execute(Actor $actor, int $id, ?string $text, ?array $options): ScreeningQuestion
    {
        $member = $this->access->permitted($actor, CompanyPermission::ScreeningQuestionsManage);
        $this->switch->assertOn($member->companyId);

        $text = $text === null ? null : QuestionRules::text($text);
        $now = $this->clock->now();

        $this->transactions->run(function () use ($member, $id, $text, $options, $now): void {
            $this->questions->lock($id);
            $question = $this->questions->find($id);

            if ($question === null || $question->companyId !== $member->companyId) {
                throw EntityNotFound::of('Screening question', $id);
            }

            if ($this->usage->isLocked($id)) {
                throw new StateConflict('Applications depend on this question, so it can only be archived.', 'question-in-use');
            }

            if ($options !== null) {
                $this->changeOptions($question, $options, $now);
            }

            if ($text !== null) {
                $this->questions->updateText($id, $text, $now);
            }
        });

        return $this->questions->find($id) ?? throw EntityNotFound::of('Screening question', $id);
    }

    /**
     * @param  list<array{id: int|null, label: string}>  $options
     *
     * @throws BusinessRuleViolation
     * @throws StateConflict
     */
    private function changeOptions(ScreeningQuestion $question, array $options, \DateTimeImmutable $now): void
    {
        $labels = QuestionRules::options($question->type, array_column($options, 'label'));

        if (! $question->type->hasOptions()) {
            return;
        }

        $own = $question->optionIds();
        $kept = [];

        foreach ($options as $index => $option) {
            if ($option['id'] === null) {
                continue;
            }

            if (! in_array($option['id'], $own, true) || in_array($option['id'], $kept, true)) {
                throw new BusinessRuleViolation(
                    'Every option id must be a different option of this question.',
                    'invalid-screening-question',
                    extensions: ['errors' => ["options.{$index}.id" => ['This is not an option of the question, or it is listed twice.']]],
                );
            }

            $kept[] = $option['id'];
        }

        if (array_diff($this->questions->idealOptionIds($question->id), $kept) !== []) {
            throw new StateConflict('A job uses a choice that would be deleted as its ideal answer.', 'question-in-use');
        }

        $this->questions->replaceOptions(
            $question->id,
            array_map(static fn (array $option, string $label): array => ['id' => $option['id'], 'label' => $label], $options, $labels),
            $now,
        );
    }
}
