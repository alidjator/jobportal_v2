<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewQuestionRepository;
use Src\Recruitment\Domain\Recommendation;
use Src\Recruitment\Domain\Scorecard;
use Src\Recruitment\Domain\ScorecardRepository;
use Src\Recruitment\Domain\ScorecardRevision;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Saves the caller's scorecard of an interview: a draft while it is not sent, and an edit of the sent one afterwards. The content replaces the previous
 * content. An edit of a sent scorecard keeps the previous version as a revision and notes whether other scorecards were visible at the time.
 */
final readonly class SaveScorecard
{
    public function __construct(
        private TeamInterviewLookup $lookup,
        private ScorecardAccess $access,
        private ScorecardRepository $scorecards,
        private InterviewQuestionRepository $questions,
        private ScorecardViews $views,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  list<array{question_id: int, score: ?int, note: ?string}>  $answers
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `not-on-panel`
     * @throws EntityNotFound
     * @throws StateConflict `application-final` or `scoring-not-open`
     * @throws BusinessRuleViolation `invalid-scorecard` or `recommendation-required`
     */
    public function execute(Actor $actor, int $interviewId, ?Recommendation $recommendation, ?string $note, array $answers): ScorecardView
    {
        $saved = $this->transactions->run(function () use ($actor, $interviewId, $recommendation, $note, $answers): Scorecard {
            $team = $this->lookup->find($actor, $interviewId, forUpdate: true, permission: CompanyPermission::InterviewsManage);
            $this->access->assertOnPanel($team);
            $now = $this->clock->now();
            $this->access->assertOpen($team, $now);

            $weights = [];

            foreach ($this->questions->withIds($interviewId) as $item) {
                $weights[$item->id] = $item->question->weight;
            }

            $memberId = $team->member->userId;
            $current = $this->scorecards->find($interviewId, $memberId) ?? Scorecard::blank($interviewId, $memberId);
            $updated = $current->withContent($recommendation, $note, $answers, $weights, $now);

            if ($current->isSubmitted() && $current->content() !== $updated->content()) {
                $this->scorecards->addRevision(new ScorecardRevision(
                    (int) $current->id,
                    $current->content(),
                    array_diff($this->scorecards->sentBy($interviewId), [$memberId]) !== [],
                    $now,
                ));
            }

            return $this->scorecards->save($updated);
        });

        return $this->views->one($saved);
    }
}
