<?php

namespace Src\TalentPool\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\TalentPool\Application\EntryView;
use Src\TalentPool\Domain\EntrySource;

/**
 * A saved person with a summary. While the entry is not available (the person can no longer be found and no consent within 24 months keeps them), `candidate`, `note`
 * and every summary field are null or empty and `available` is false; the entry is only kept so it can be removed. `candidate.slug` is null for a person kept through
 * consent alone: there is no profile to open, only the summary and the entry page. An entry of the `legacy` source (a private list carried over from karir.pkp) shows `candidate.name`
 * even while it is not available, with a null slug, and nothing else about the person: no summary, no note, no contact or invitation.
 *
 * @property EntryView $resource
 */
final class EntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            'id' => $view->id,
            'available' => $view->isAvailable(),
            /** @var array{slug: string|null, name: string|null}|null */
            'candidate' => $view->isAvailable() || ($view->source === EntrySource::Legacy && $view->candidateName !== null) ? ['slug' => $view->candidateSlug, 'name' => $view->candidateName] : null,
            'category' => $view->categoryId === null || $view->categoryName === null ? null : ['id' => $view->categoryId, 'name' => $view->categoryName],
            'note' => $view->note,
            'source' => $view->source->value,
            /** @var array{id: int, name: string|null}|null null when unknown */
            'saved_by' => $view->savedById === null ? null : ['id' => $view->savedById, 'name' => $view->savedByName],
            'saved_at' => $view->createdAt->format(DateTimeInterface::ATOM),
            'headline' => $view->headline,
            'city' => $view->city,
            /** @var array{title: string, employer: string}|null */
            'latest_position' => $view->latestPosition,
            /** @var list<string> up to five */
            'skills' => $view->skills,
            /** @var array{id: int|null, job_title: string|null, status: string, applied_at: string}|null `id` only when the member may see the application */
            'last_application' => $view->lastApplication === null ? null : [
                'id' => $view->lastApplication->id,
                'job_title' => $view->lastApplication->jobTitle,
                'status' => $view->lastApplication->status,
                'applied_at' => $view->lastApplication->appliedAt->format(DateTimeInterface::ATOM),
            ],
            /** @var array{job_title: string|null, status: string, sent_at: string}|null */
            'last_invitation' => $view->lastInvitation === null ? null : [
                'job_title' => $view->lastInvitation->jobTitle,
                'status' => $view->lastInvitation->status,
                'sent_at' => $view->lastInvitation->sentAt->format(DateTimeInterface::ATOM),
            ],
        ];
    }
}
