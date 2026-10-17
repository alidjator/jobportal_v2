<?php

namespace Src\Dashboard\Interface\Http\Resources;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Application\Calendar\CalendarEvent;

/**
 * One calendar event. An all-day event (the start date of an offer) gives `starts_at` as the Jakarta day (`Y-m-d`); every other time is ISO 8601. `actions` says what the caller may do now,
 * each through the endpoint that already exists; the calendar itself only reads.
 *
 * @property CalendarEvent $resource
 */
final class CalendarEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        return [
            'id' => $event->id,
            /** @var 'interview'|'assessment_due'|'offer_deadline'|'start_date' */
            'type' => $event->type,
            'title' => $event->title,
            'starts_at' => $event->allDay ? $event->startsAt->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d') : $event->startsAt->format(DateTimeInterface::ATOM),
            'ends_at' => $event->endsAt?->format(DateTimeInterface::ATOM),
            'all_day' => $event->allDay,
            'status' => $event->status,
            'application_id' => $event->applicationId,
            'job' => ['id' => $event->jobId, 'title' => $event->jobTitle],
            'candidate_name' => $event->candidateName,
            /** What is specific to the type: the format, the place, the interviewer and the panel of an interview; the position of an offer. */
            'details' => $event->details,
            'involves_me' => $event->involvesMe,
            /** @var list<'view_application'|'edit'|'edit_note'|'cancel'|'scorecard'|'download_ics'> */
            'actions' => $event->actions,
        ];
    }
}
