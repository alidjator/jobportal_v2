<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningQuestion;
use Src\Recruitment\Domain\ScreeningQuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One library question, archived or not. A question of another company does not exist. */
final readonly class GetScreeningQuestion
{
    public function __construct(private RecruitmentAccess $access, private ScreeningQuestionRepository $questions) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): ScreeningQuestion
    {
        $member = $this->access->anyOf($actor, CompanyPermission::JobsCreate, CompanyPermission::ScreeningQuestionsManage);
        $question = $this->questions->find($id);

        if ($question === null || $question->companyId !== $member->companyId) {
            throw EntityNotFound::of('Screening question', $id);
        }

        return $question;
    }
}
