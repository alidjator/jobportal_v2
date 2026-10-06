<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\QuestionRules;
use Src\Recruitment\Domain\QuestionType;
use Src\Recruitment\Domain\ScreeningQuestion;
use Src\Recruitment\Domain\ScreeningQuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A company admin adds a question to the library. The flags that decide how a job uses it are set when it is attached to a job. */
final readonly class CreateScreeningQuestion
{
    public function __construct(
        private RecruitmentAccess $access,
        private ScreeningSwitch $switch,
        private ScreeningQuestionRepository $questions,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  list<string>  $labels  the choices, for a choice question
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-admin-required` or `screening-disabled`
     * @throws BusinessRuleViolation `invalid-screening-question`
     */
    public function execute(Actor $actor, QuestionType $type, string $text, array $labels): ScreeningQuestion
    {
        $member = $this->access->companyAdmin($actor);
        $this->switch->assertOn($member->companyId);

        $text = QuestionRules::text($text);
        $labels = QuestionRules::options($type, $labels);
        $now = $this->clock->now();

        $id = $this->transactions->run(fn (): int => $this->questions->create($member->companyId, $type, $text, $labels, $member->userId, $now));

        return $this->questions->find($id) ?? throw new \LogicException('The new question was not found.');
    }
}
