<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\RsvpAnswer;

/**
 * The answer of a panel member to an interview invitation. `answer` is `pending`, `accepted` or `declined`; `at` is the time of the answer (null while pending).
 *
 * @property RsvpAnswer $resource
 */
final class InterviewRsvpResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'answer' => $this->resource->rsvp->value,
            'note' => $this->resource->note,
            'at' => $this->resource->at?->format(DATE_ATOM),
        ];
    }
}
