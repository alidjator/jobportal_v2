<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\RecruiterApplicationRow;

/**
 * One line of the applications of a job. The applicant fields are null for an applicant whose account was anonymised, flagged by `is_deleted`. Email and phone
 * are left to the detail.
 *
 * @property RecruiterApplicationRow $resource
 */
final class CompanyApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'id' => $row->id,
            'applicant' => [
                'is_deleted' => $row->isDeleted,
                'name' => $row->name,
                'headline' => $row->headline,
                'latest_experience' => $row->latestExperience,
                'city' => $row->city,
                'expected_salary' => $row->expectedSalary,
                'age' => $row->age,
            ],
            'meets_must_have' => $row->meetsMustHave,
            'rating' => ['average' => $row->ratingAverage, 'count' => $row->ratingCount],
            /** @var array{score: float|null, yes: int, no: int}|null */
            'interview_result' => $row->interviewResult === null ? null : ['score' => $row->interviewResult->score, 'yes' => $row->interviewResult->yes(), 'no' => $row->interviewResult->no()],
            'stage' => ['id' => $row->stageId, 'type' => $row->stageType->value, 'name' => $row->stageName],
            'applied_at' => $row->appliedAt->format(DATE_ATOM),
            'stage_entered_at' => $row->stageEnteredAt?->format(DATE_ATOM),
            'days_in_stage' => $row->daysInStage,
            'stale' => $row->stale,
            /** @var 'not_scheduled'|'pending'|'confirmed'|'completed'|null null outside an interview stage */
            'interview_state' => $row->interviewState,
            /** @var array{pending: int, declined: int}|null how many of the panel of the active interview have not answered the invitation and how many cannot attend; null without an active interview */
            'interview_rsvp' => $row->interviewRsvp,
            'other_applications_count' => $row->otherApplicationsCount,
        ];
    }
}
