<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\QuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The psychologist scores one drawing from 0 to the points of its question (set by the author of the battery; two decimals) and may leave a note. Scoring
 * again replaces the earlier score. The score is the psychologist's own record: it does not feed the scale scores.
 */
final readonly class ScoreDrawing
{
    public const int NOTE_MAX = 2000;

    public function __construct(
        private ReviewAccess $access,
        private AnswerRepository $answers,
        private QuestionRepository $questions,
        private ReviewViews $views,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `report-signed`
     * @throws BusinessRuleViolation `invalid-score`
     */
    public function execute(Actor $actor, int $reportId, int $questionId, float $score, ?string $note): ReviewView
    {
        return $this->transactions->run(function () use ($actor, $reportId, $questionId, $score, $note): ReviewView {
            $psychologist = $this->access->psychologist($actor);
            $report = $this->access->report($psychologist, $reportId, forUpdate: true);

            if ($report->isSigned()) {
                throw new StateConflict('This report is signed and cannot be changed.', 'report-signed');
            }

            if (! isset($this->answers->drawingsOf($report->attemptId)[$questionId])) {
                throw EntityNotFound::of('Drawing', $questionId);
            }

            $max = $this->questions->find($questionId)->points ?? 0;

            if ($score < 0 || $score > $max) {
                $message = 'The score must be from 0 to :max.';

                throw new BusinessRuleViolation($message, 'invalid-score', extensions: ['errors' => ['score' => [$message]]], parameters: ['max' => $max]);
            }

            $note = $note === null || trim($note) === '' ? null : trim($note);
            $this->answers->scoreDrawing($report->attemptId, $questionId, round($score, 2), $note);

            return $this->views->build($psychologist, $report);
        });
    }
}
