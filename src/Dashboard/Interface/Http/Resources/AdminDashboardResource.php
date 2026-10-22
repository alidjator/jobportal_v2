<?php

namespace Src\Dashboard\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Dashboard\Application\DashboardView;

/**
 * The dashboard of an admin. A work-queue card is absent when the admin lacks the permission of its module.
 *
 * @property DashboardView $resource
 */
final class AdminDashboardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'generated_at' => $this->resource->generatedAt,
            /** @var array{job_seekers?: array{total: int, daily: list<array{day: string, count: int}>}, companies?: array{total: int, active: int, daily: list<array{day: string, count: int}>}, jobs?: array{total: int, live: int, daily: list<array{day: string, count: int}>}, applications?: array{total: int, daily: list<array{day: string, count: int}>}, operations?: array{failed_last_24_hours: int, failed_by_queue: array<string, int>, waiting: int, waiting_by_queue: array<string, int>, scheduler: array{last_tick_at: string|null, tasks: list<array{task: string, last_finished_at: string|null, late: bool, failing: bool}>}}, activation?: array{job_seekers_signed_in_percent: float|null, companies_signed_in_percent: float|null}, companies_queue?: array{waiting_for_review: int, name_changes_waiting: int, without_admin: int, without_admin_companies: list<array{id: int, name: string}>}, name_changes_queue?: array{pending: int}, jobs_queue?: array{waiting_for_approval: int, job_reports: array{jobs_with_open_reports: int, held: int}}, reviews_queue?: array{pending_reviews: int, pending_replies: int, open_reports: int}, chat_reports_queue?: array{open_reports: int, oldest_open_at: string|null}} */
            'cards' => (object) $this->resource->cards,
        ];
    }
}
