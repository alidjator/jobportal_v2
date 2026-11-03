<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\InterviewComparisonRow;
use Src\Recruitment\Application\QuestionScore;

/**
 * One candidate of the comparison with the result of their latest scored interview. A locked row (the caller is on the panel and has not sent their
 * scorecard) keeps the application and the interview but has `null` for every result field.
 *
 * @property InterviewComparisonRow $resource
 */
final class InterviewComparisonRowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $application = $row->application;
        $result = $row->interview->result;

        return [
            'application' => [
                'id' => $application->id,
                'is_deleted' => $application->isDeleted,
                'name' => $application->isDeleted ? 'Deleted user' : $application->name,
                'stage' => ['id' => $application->stageId, 'type' => $application->stageType->value, 'name' => $application->stageName],
            ],
            'interview' => [
                'id' => $row->interview->interviewId,
                'starts_at' => $row->interview->startsAt->format(DATE_ATOM),
                'kit' => $row->interview->kitId === null ? null : ['id' => $row->interview->kitId, 'name' => $row->interview->kitName],
            ],
            'locked' => $row->interview->locked,
            /** the average score of the sent scorecards */
            'score' => $result?->score,
            'recommendations' => $result?->recommendations,
            'scorecards_sent' => $result?->scorecardsSent,
            'panel_size' => $row->panelSize,
            /** @var list<array{question: string, average: float|null, count: int}>|null one cell per column of the kit, in the order of the columns */
            'cells' => $row->cells === null ? null : array_map(
                static fn (QuestionScore $cell): array => ['question' => $cell->text, 'average' => $cell->average, 'count' => $cell->count],
                $row->cells,
            ),
            /** @var array{kind: 'skill'|'psychological', test_title: string, submitted_at: string, status: string, percentage: float|null, band: string|null, recommendation: string|null, recommendation_label: string|null}|null the latest submitted test of the applicant, only when the caller may read its result (a psychological battery shows its state and recommendation, never a score) */
            'assessment' => $row->assessment?->toArray(),
            /** how well the applicant fitted the job when applying, 0 to 100 */
            'match_score' => $application->matchScore,
            'rating_average' => $application->ratingAverage,
        ];
    }
}
