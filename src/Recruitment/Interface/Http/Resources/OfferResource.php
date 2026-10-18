<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\OfferView;
use Src\Recruitment\Domain\OfferAllowance;
use Src\Recruitment\Domain\OfferTerms;

/**
 * An offer, for the team and for the job seeker alike. `status` is as of now: a pending offer past its deadline is `expired`. `answer_note` is the note of a
 * job seeker who declined. `terms` and `letter` are null for an offer sent before they existed; `letter.sha256` is the SHA-256 of the letter file.
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
            'terms' => $this->terms($offer->terms),
            'letter' => $offer->letter === null ? null : ['source' => $offer->letter->source->value, 'sha256' => $offer->letter->sha256],
        ];
    }

    /**
     * The terms, or null for an offer sent before they existed. The benefits keep their names in both languages as they were when the offer was sent.
     *
     * @return array<string, mixed>|null
     */
    private function terms(?OfferTerms $terms): ?array
    {
        return $terms === null ? null : [
            'employment_type' => $terms->employmentType->value,
            'contract_months' => $terms->contractMonths,
            'probation_months' => $terms->probationMonths,
            'work_mode' => $terms->workMode->value,
            'work_location' => $terms->workLocation,
            'salary_basis' => $terms->salaryBasis->value,
            'allowances' => array_map(static fn (OfferAllowance $line): array => ['name' => $line->name, 'amount' => $line->amount], $terms->allowances),
            'allowances_total' => $terms->allowancesTotal(),
            'benefits' => $terms->benefits,
        ];
    }
}
