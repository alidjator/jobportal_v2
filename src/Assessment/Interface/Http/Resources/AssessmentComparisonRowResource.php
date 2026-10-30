<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Comparison\AssessmentComparisonRow;
use Src\Assessment\Domain\TestKind;

/**
 * One candidate of the comparison with the result of their latest submitted test. A skill test shows its percentage, band and one cell per section; a battery shows
 * `awaiting_psychologist_review` and nothing else until a psychologist signs, then the band of each scale and the number of the report.
 *
 * @property AssessmentComparisonRow $resource
 */
final class AssessmentComparisonRowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $application = $row->application;
        $assessment = $row->assessment;
        $skill = $row->kind === TestKind::Skill;

        return [
            'application' => [
                'id' => $application->applicationId,
                'is_deleted' => $application->isDeleted,
                'name' => $application->isDeleted ? 'Deleted user' : $application->name,
                'stage' => ['id' => $application->stageId, 'type' => $application->stageType, 'name' => $application->stageName],
            ],
            'invitation' => [
                'id' => $assessment->invitationId,
                'submitted_at' => $assessment->submittedAt->format(DateTimeInterface::ATOM),
                'test' => ['id' => $assessment->testId, 'name' => $row->testName, 'kind' => $row->kind->value],
            ],
            /** `scored` or `pending_scoring` for a skill test; `awaiting_psychologist_review` or `signed` for a psychological battery */
            'status' => $row->status,
            /** the share of the points earned, null while a written answer waits for its assessor and for a battery */
            'percentage' => $skill ? $assessment->percentage : null,
            'band' => $skill ? $assessment->band?->value : null,
            /** how many integrity signals the attempt raised, as a tier (`none`, `some`, `major`) */
            'integrity_tier' => $assessment->integrityTier?->value,
            /** one cell per section of the compared test, in the order of the columns; null for another test */
            'sections' => $row->sections,
            /** the bands of a signed battery, one per scale; null before signing and for another test */
            'scales' => $row->scales,
            'report_number' => $assessment->reportNumber,
            /** how well the applicant fitted the job when applying, 0 to 100 */
            'match_score' => $application->matchScore,
            'rating_average' => $application->ratingAverage,
            /** the average score of the latest interview, null without one or while the caller has not sent their scorecard */
            'interview_score' => $application->interviewScore,
        ];
    }
}
