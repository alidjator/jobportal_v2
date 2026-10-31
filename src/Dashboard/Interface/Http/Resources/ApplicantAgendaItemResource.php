<?php

namespace Src\Dashboard\Interface\Http\Resources;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Dashboard\Application\ApplicantAgendaEntry;

/**
 * One date of the agenda of the job seeker. An all-day date (the join date) gives `starts_at` as the Jakarta day (`Y-m-d`); every other time is ISO 8601. The screen builds the link from `type` and the ids in `details`
 * and `application_id`.
 *
 * @property ApplicantAgendaEntry $resource
 */
final class ApplicantAgendaItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource->item;

        return [
            'id' => $item->id,
            /** @var 'interview'|'offer_deadline'|'join_date'|'assessment_window'|'check' */
            'type' => $item->type,
            'starts_at' => $item->allDay ? $item->startsAt->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d') : $item->startsAt->format(DateTimeInterface::ATOM),
            'ends_at' => $item->endsAt?->format(DateTimeInterface::ATOM),
            'all_day' => $item->allDay,
            'status' => $item->status,
            'application_id' => $item->applicationId,
            'job' => ['id' => $item->jobId, 'title' => $this->resource->jobTitle],
            'company_name' => $this->resource->companyName,
            /** What is specific to the type: the format, the place and the time asked for of an interview; the position of an offer; the name, place and vendor of a check. */
            'details' => $item->details,
        ];
    }
}
