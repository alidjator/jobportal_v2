<?php

namespace Src\Dashboard\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Dashboard\Application\DashboardView;

/**
 * The dashboard of a company member. A card is absent when the member lacks its permission.
 *
 * @property DashboardView $resource
 */
final class CompanyDashboardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'generated_at' => $this->resource->generatedAt,
            /** @var array{jobs?: array{by_status: array{draft: int, pending_review: int, active: int, closed: int, expired: int, rejected: int, taken_down: int}, total: int}, applications?: array{new: int, by_stage: array{applied: int, screening: int, assessment: int, interview: int, offer: int, hired: int, rejected: int, withdrawn: int}}, schedule?: array{interviews: list<array{id: int, application_id: int, job_id: int, starts_at: string, format: 'online'|'onsite'|'video'|'voice', status: string, interviewer_id: int}>, offers_waiting: int}, job_performance?: array{jobs: list<array{job_id: int, title: string, status: string, views: int, applications: int, applications_per_view: float|null}>, views_daily: list<array{day: string, count: int}>, applications_daily: list<array{day: string, count: int}>}, time_to_fill?: array{period_days: int, filled_jobs: int, median_days: float|null, open_jobs: int}, messages?: array{unread_conversations: int}, notifications?: array{unread: int}} */
            'cards' => (object) $this->resource->cards,
        ];
    }
}
