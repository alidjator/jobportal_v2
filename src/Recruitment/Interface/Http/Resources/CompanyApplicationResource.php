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
            'stage' => ['id' => $row->stageId, 'type' => $row->stageType->value, 'name' => $row->stageName],
            'applied_at' => $row->appliedAt->format(DATE_ATOM),
        ];
    }
}
