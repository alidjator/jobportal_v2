<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningQuestion;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The library of the caller's company, for every member: the active questions, or the archived ones. */
final readonly class ListScreeningQuestions
{
    public function __construct(private RecruitmentAccess $access, private ScreeningQuestionReader $reader) {}

    /**
     * @return Page<ScreeningQuestion>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function execute(Actor $actor, bool $archived, ?string $search, PageRequest $request): Page
    {
        return $this->reader->page(new ScreeningQuestionListQuery($this->access->member($actor)->companyId, $archived, $search), $request);
    }
}
