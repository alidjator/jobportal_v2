<?php

namespace Src\Dashboard\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Dashboard\Application\DashboardView;

/**
 * The dashboard of a job seeker.
 *
 * @property DashboardView $resource
 */
final class JobSeekerDashboardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'generated_at' => $this->resource->generatedAt,
            /** @var array{profile?: array{completeness_percent: int, empty_sections: list<string>, viewed_by_companies: int}, applications?: array{by_stage: array{applied: int, screening: int, assessment: int, interview: int, offer: int, hired: int, rejected: int, withdrawn: int}, active: int, recent: list<array{id: int, job_id: int, job_title: string|null, stage_type: string, applied_at: string}>}, interviews?: array{upcoming: list<array{id: int, application_id: int, job_id: int, starts_at: string, format: 'online'|'onsite'|'video'|'voice', status: string}>}, invitations?: array{new: int, pending: int}, messages?: array{unread_conversations: int}, notifications?: array{unread: int}} */
            'cards' => (object) $this->resource->cards,
        ];
    }
}
