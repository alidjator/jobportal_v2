<?php

namespace Src\Recruitment\Infrastructure\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\OfferStatus;
use Src\Shared\Application\Calendar\ApplicantAgendaItem;
use Src\Shared\Application\Calendar\ApplicantAgendaSource;
use Src\Shared\Application\Settings\Settings;

/**
 * The dates of a job seeker in Recruitment: their interviews (pending and confirmed, with the format, the place or whether the meeting is held in the platform, and the time they proposed, if any), the deadline of an offer
 * they have not answered, the join date of an accepted offer (all day, a Jakarta day) and the schedule of a pre-employment check (none while the feature is switched off, none for one that is closed or whose consent was refused).
 * Applications that are rejected or withdrawn, and applicants whose data was anonymised, give nothing. The link of a meeting and the notes of the team stay out.
 */
final readonly class ApplicantRecruitmentAgenda implements ApplicantAgendaSource
{
    private const string ZONE = 'Asia/Jakarta';

    public function __construct(private ConnectionInterface $connection, private Settings $settings) {}

    public function items(int $userId, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array
    {
        $start = $this->utc($from);
        $end = $this->utc($to);
        $items = [];

        $interviews = $this->live($this->connection->table('recruitment_interviews as i')->join('recruitment_applications as a', 'a.id', '=', 'i.application_id'), $userId)
            ->whereIn('i.status', [InterviewStatus::Pending->value, InterviewStatus::Confirmed->value])
            ->where('i.starts_at', '<', $end)->where('i.ends_at', '>', $start)
            ->orderBy('i.starts_at')->orderBy('i.id')->limit($limit)
            ->get(['i.id', 'i.application_id', 'a.job_id', 'a.company_id', 'i.starts_at', 'i.ends_at', 'i.status', 'i.format', 'i.location', 'i.proposed_starts_at']);

        foreach ($interviews as $row) {
            $format = (string) $row->format;
            $proposed = $row->proposed_starts_at === null ? null : $this->time((string) $row->proposed_starts_at);

            $items[] = $this->item("interview:{$row->id}", ApplicantAgendaItem::INTERVIEW, $row, $this->time((string) $row->starts_at), $this->time((string) $row->ends_at), false, [
                'interview_id' => (int) $row->id,
                'format' => $format,
                'location' => $row->location === null ? null : (string) $row->location,
                /** A video or voice interview is held in the platform: there is a room, entered from the interview page. */
                'has_room' => in_array($format, [InterviewFormat::Video->value, InterviewFormat::Voice->value], true),
                'proposed_starts_at' => $proposed?->format(DATE_ATOM),
            ]);
        }

        $deadlines = $this->live($this->connection->table('recruitment_offers as o')->join('recruitment_applications as a', 'a.id', '=', 'o.application_id'), $userId)
            ->where('o.status', OfferStatus::Pending->value)
            ->where('o.expires_at', '>=', $start)->where('o.expires_at', '<', $end)
            ->orderBy('o.expires_at')->orderBy('o.id')->limit($limit)
            ->get(['o.id', 'o.application_id', 'a.job_id', 'a.company_id', 'o.expires_at', 'o.status', 'o.position']);

        foreach ($deadlines as $row) {
            $items[] = $this->item("offer_deadline:{$row->id}", ApplicantAgendaItem::OFFER_DEADLINE, $row, $this->time((string) $row->expires_at), null, false, ['offer_id' => (int) $row->id, 'position' => (string) $row->position]);
        }

        $zone = new DateTimeZone(self::ZONE);
        $joins = $this->live($this->connection->table('recruitment_offers as o')->join('recruitment_applications as a', 'a.id', '=', 'o.application_id'), $userId)
            ->where('a.stage_type', 'hired')->where('o.status', OfferStatus::Accepted->value)
            ->where('o.start_date', '>=', $from->setTimezone($zone)->format('Y-m-d'))->where('o.start_date', '<', $to->setTimezone($zone)->format('Y-m-d'))
            ->orderBy('o.start_date')->orderBy('o.id')->limit($limit)
            ->get(['o.id', 'o.application_id', 'a.job_id', 'a.company_id', 'o.start_date', 'o.status', 'o.position']);

        foreach ($joins as $row) {
            $items[] = $this->item("join_date:{$row->id}", ApplicantAgendaItem::JOIN_DATE, $row, new DateTimeImmutable(substr((string) $row->start_date, 0, 10).' 00:00:00', $zone), null, true, ['offer_id' => (int) $row->id, 'position' => (string) $row->position]);
        }

        if ($this->settings->boolean(RecruitmentSettings::PRE_EMPLOYMENT_CHECKS_ENABLED)) {
            $checks = $this->live($this->connection->table('recruitment_application_checks as c')->join('recruitment_applications as a', 'a.id', '=', 'c.application_id')->join('recruitment_check_types as t', 't.id', '=', 'c.check_type_id'), $userId)
                ->whereIn('c.status', ['requested', 'consented', 'scheduled'])
                ->whereNull('c.consent_refused_at')
                ->where('c.scheduled_at', '>=', $start)->where('c.scheduled_at', '<', $end)
                ->orderBy('c.scheduled_at')->orderBy('c.id')->limit($limit)
                ->get(['c.id', 'c.application_id', 'a.job_id', 'a.company_id', 'c.scheduled_at', 'c.status', 'c.place', 'c.vendor', 'c.consent_required', 'c.consented_at', 't.name']);

            foreach ($checks as $row) {
                $items[] = $this->item("check:{$row->id}", ApplicantAgendaItem::CHECK, $row, $this->time((string) $row->scheduled_at), null, false, [
                    'check_id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'place' => $row->place === null ? null : (string) $row->place,
                    'vendor' => $row->vendor === null ? null : (string) $row->vendor,
                    'awaiting_consent' => (bool) $row->consent_required && $row->consented_at === null,
                ]);
            }
        }

        return $items;
    }

    /** The applications of the account that are still in play and whose applicant is still known. */
    private function live(Builder $query, int $userId): Builder
    {
        return $query->where('a.user_id', $userId)->whereNotNull('a.profile_snapshot')->whereNotIn('a.stage_type', ['rejected', 'withdrawn']);
    }

    /** @param  array<string, mixed>  $details */
    private function item(string $id, string $type, object $row, DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt, bool $allDay, array $details): ApplicantAgendaItem
    {
        return new ApplicantAgendaItem($id, $type, $startsAt, $endsAt, $allDay, (string) $row->status, (int) $row->application_id, (int) $row->job_id, (int) $row->company_id, $details);
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
