<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\OfferView;

/**
 * An offer, for the team and for the job seeker alike. `status` is as of now: a pending offer past its deadline is `expired`. `answer_note` is the note of a
 * job seeker who declined.
 *
 * @property OfferView $resource
 */
final class OfferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $offer = $this->resource->offer;

        return [
            'id' => $offer->id,
            'application_id' => $offer->applicationId,
            'status' => $this->resource->status->value,
            'position' => $offer->position,
            'salary' => $offer->salary,
            'start_date' => $offer->startDate->format('Y-m-d'),
            'note' => $offer->note,
            'expires_at' => $offer->expiresAt->format(DATE_ATOM),
            'answer_note' => $offer->answerNote,
            'answered_at' => $offer->answeredAt?->format(DATE_ATOM),
            'created_by' => $offer->createdBy,
            'created_at' => $offer->createdAt->format(DATE_ATOM),
        ];
    }
}
