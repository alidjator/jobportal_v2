<?php

namespace Src\Job\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\JobDetail;
use Src\Job\Domain\JobStatus;

/**
 * A job as its company sees it: every field as stored (ids for the classification and the location, Markdown description), the skills and benefits with
 * their names, the tags as text and the assignees. The reason of a rejection or a take-down shows only in those two statuses.
 *
 * @property JobDetail $resource
 */
final class JobDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $job = $this->resource->job;
        $content = $job->content;

        return [
            'id' => $job->id,
            'slug' => $job->slug.'-'.$job->id,
            'title' => $content->title,
            'status' => $job->status->value,
            ...($job->status === JobStatus::Rejected || $job->status === JobStatus::TakenDown ? ['status_reason' => $job->statusReason] : []),
            'description' => $content->description,
            'category_id' => $content->categoryId,
            'role_id' => $content->roleId,
            'job_type_id' => $content->jobTypeId,
            'job_level_id' => $content->jobLevelId,
            'work_time_id' => $content->workTimeId,
            'experience_level_id' => $content->experienceLevelId,
            'education_level_id' => $content->educationLevelId,
            'vacancies' => $content->vacancies,
            'deadline' => $content->deadline?->format('Y-m-d'),
            'salary_min' => $content->salaryMin,
            'salary_max' => $content->salaryMax,
            'salary_period_id' => $content->salaryPeriodId,
            'salary_visible' => $content->salaryVisible,
            'work_mode' => $content->workMode?->value,
            'province_id' => $content->provinceId,
            'city_id' => $content->cityId,
            'subdistrict_id' => $content->subdistrictId,
            'village_id' => $content->villageId,
            'postal_code_id' => $content->postalCodeId,
            'address_line' => $content->addressLine,
            'skills' => $this->resource->skills,
            'tags' => $content->tags,
            'benefits' => $this->resource->benefits,
            'assignees' => $this->resource->assignees,
            'published_at' => $job->publishedAt?->format(DateTimeInterface::ATOM),
            // "Sedang ditinjau admin": set while the job is on a report hold. Never who reported it or why.
            'report_hold_at' => $job->reportHoldAt?->format(DateTimeInterface::ATOM),
            'reviewed_at' => $job->reviewedAt?->format(DateTimeInterface::ATOM),
            'created_by_user_id' => $job->createdByUserId,
            'created_at' => $job->createdAt?->format(DateTimeInterface::ATOM),
            'updated_at' => $job->updatedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
