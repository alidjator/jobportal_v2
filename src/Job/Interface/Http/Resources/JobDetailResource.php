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
            'title' => $content->title,
            /** @var JobStatus */
            'status' => $job->status->value,
            /** Only for `rejected` and `taken_down`. */
            'status_reason' => $this->when($job->status === JobStatus::Rejected || $job->status === JobStatus::TakenDown, $job->statusReason),
            /** One line for search results and sharing (20 to 150 characters). */
            'summary' => $content->summary,
            'description' => $content->description,
            'category_id' => $content->categoryId,
            /** The name in the request language. */
            'category_name' => $this->resource->names['category'] ?? null,
            'role_id' => $content->roleId,
            /** The name in the request language. */
            'role_name' => $this->resource->names['role'] ?? null,
            'job_type_id' => $content->jobTypeId,
            /** The name in the request language. */
            'job_type_name' => $this->resource->names['job_type'] ?? null,
            'job_level_id' => $content->jobLevelId,
            /** The name in the request language. */
            'job_level_name' => $this->resource->names['job_level'] ?? null,
            'work_time_id' => $content->workTimeId,
            /** The name in the request language. */
            'work_time_name' => $this->resource->names['work_time'] ?? null,
            'experience_level_id' => $content->experienceLevelId,
            /** The name in the request language. */
            'experience_level_name' => $this->resource->names['experience_level'] ?? null,
            'education_level_id' => $content->educationLevelId,
            /** The name in the request language. */
            'education_level_name' => $this->resource->names['education_level'] ?? null,
            'vacancies' => $content->vacancies,
            /** Whether the public sees the number of vacancies; the company always does. */
            'vacancies_visible' => $content->vacanciesVisible,
            /** Days after a rejection before the same person may apply again; null means never. */
            'reapply_after_days' => $content->reapplyAfterDays,
            'hires_count' => $this->resource->hiresCount,
            'filled' => $this->resource->filled(),
            'deadline' => $content->deadline?->format('Y-m-d'),
            'salary_min' => $content->salaryMin,
            'salary_max' => $content->salaryMax,
            'salary_period_id' => $content->salaryPeriodId,
            /** The name in the request language. */
            'salary_period_name' => $this->resource->names['salary_period'] ?? null,
            'salary_visible' => $content->salaryVisible,
            'salary_note' => $content->salaryNote,
            'work_mode' => $content->workMode?->value,
            'province_id' => $content->provinceId,
            'province_name' => $this->resource->location?->province,
            'city_id' => $content->cityId,
            'city_name' => $this->resource->location?->city,
            'subdistrict_id' => $content->subdistrictId,
            'subdistrict_name' => $this->resource->location?->subdistrict,
            'village_id' => $content->villageId,
            'village_name' => $this->resource->location?->village,
            'postal_code_id' => $content->postalCodeId,
            /** "Subdistrict, City, Province", ready to show. */
            'location_label' => $this->resource->locationLabel(),
            'address_line' => $content->addressLine,
            /** @var list<int> The company locations of the job, the main one first; the address above is that of the first one. */
            'location_ids' => $content->locationIds(),
            'locations' => $this->resource->places,
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
