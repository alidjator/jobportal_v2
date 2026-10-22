<?php

namespace Src\Job\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\JobDetail;
use Src\Job\Domain\JobStatus;

/**
 * A job as its company sees it: every field as stored (ids for the classification and the location, each with its name and a formatted location label, Markdown description), the skills and benefits with
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
            /** @var int */
            'id' => $job->id,
            /** @var string */
            'slug' => $job->slug.'-'.$job->id,
            /** @var string */
            'title' => $content->title,
            /** @var JobStatus */
            'status' => $job->status->value,
            /** @var string|null Only for `rejected` and `taken_down`. */
            'status_reason' => $this->when($job->status === JobStatus::Rejected || $job->status === JobStatus::TakenDown, $job->statusReason),
            'description' => $content->description,
            'category_id' => $content->categoryId,
            /** @var string|null The name in the request language. */
            'category_name' => $this->resource->names['category'] ?? null,
            'role_id' => $content->roleId,
            /** @var string|null The name in the request language. */
            'role_name' => $this->resource->names['role'] ?? null,
            'job_type_id' => $content->jobTypeId,
            /** @var string|null The name in the request language. */
            'job_type_name' => $this->resource->names['job_type'] ?? null,
            'job_level_id' => $content->jobLevelId,
            /** @var string|null The name in the request language. */
            'job_level_name' => $this->resource->names['job_level'] ?? null,
            'work_time_id' => $content->workTimeId,
            /** @var string|null The name in the request language. */
            'work_time_name' => $this->resource->names['work_time'] ?? null,
            'experience_level_id' => $content->experienceLevelId,
            /** @var string|null The name in the request language. */
            'experience_level_name' => $this->resource->names['experience_level'] ?? null,
            'education_level_id' => $content->educationLevelId,
            /** @var string|null The name in the request language. */
            'education_level_name' => $this->resource->names['education_level'] ?? null,
            'vacancies' => $content->vacancies,
            'hires_count' => $this->resource->hiresCount,
            'filled' => $this->resource->filled(),
            'deadline' => $content->deadline?->format('Y-m-d'),
            'salary_min' => $content->salaryMin,
            'salary_max' => $content->salaryMax,
            'salary_period_id' => $content->salaryPeriodId,
            /** @var string|null The name in the request language. */
            'salary_period_name' => $this->resource->names['salary_period'] ?? null,
            'salary_visible' => $content->salaryVisible,
            'work_mode' => $content->workMode?->value,
            'province_id' => $content->provinceId,
            /** @var string|null */
            'province_name' => $this->resource->location?->province,
            'city_id' => $content->cityId,
            /** @var string|null */
            'city_name' => $this->resource->location?->city,
            'subdistrict_id' => $content->subdistrictId,
            /** @var string|null */
            'subdistrict_name' => $this->resource->location?->subdistrict,
            'village_id' => $content->villageId,
            /** @var string|null */
            'village_name' => $this->resource->location?->village,
            'postal_code_id' => $content->postalCodeId,
            /** @var string|null "Subdistrict, City, Province", ready to show. */
            'location_label' => $this->resource->locationLabel(),
            'address_line' => $content->addressLine,
            'skills' => $this->resource->skills,
            'tags' => $content->tags,
            'benefits' => $this->resource->benefits,
            'assignees' => $this->resource->assignees,
            'published_at' => $job->publishedAt?->format(DateTimeInterface::ATOM),
            // "Sedang ditinjau admin": set while the job is on a report hold. Never who reported it or why.
            'report_hold_at' => $job->reportHoldAt?->format(DateTimeInterface::ATOM),
            'requisition_id' => $job->requisitionId,
            'hiring_manager_from_stage_id' => $job->hiringManagerFromStageId,
            'reviewed_at' => $job->reviewedAt?->format(DateTimeInterface::ATOM),
            'created_by_user_id' => $job->createdByUserId,
            'created_at' => $job->createdAt?->format(DateTimeInterface::ATOM),
            'updated_at' => $job->updatedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
