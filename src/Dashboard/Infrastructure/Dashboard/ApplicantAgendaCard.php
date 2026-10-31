<?php

namespace Src\Dashboard\Infrastructure\Dashboard;

use DateInterval;
use DateTimeZone;
use Src\Dashboard\Application\ApplicantAgendaEntry;
use Src\Dashboard\Application\GetApplicantAgenda;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;

/** "Jadwal mendatang": the next dates of the job seeker from now on (looking 30 days ahead) and how many fall in the next 7 days (the full agenda is `GET me/agenda`). */
final readonly class ApplicantAgendaCard implements DashboardCardProvider
{
    private const int LIMIT = 5;

    private const int AHEAD_DAYS = 30;

    private const int SOON_DAYS = 7;

    public function __construct(private GetApplicantAgenda $agenda, private Clock $clock) {}

    public function key(): string
    {
        return 'agenda';
    }

    public function audiences(): array
    {
        return [DashboardAudience::JobSeeker];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function build(DashboardScope $scope): array
    {
        $now = $this->clock->now();
        $soon = $now->add(new DateInterval('P'.self::SOON_DAYS.'D'));
        $found = $this->agenda->between($scope->userId, $now, $now->add(new DateInterval('P'.self::AHEAD_DAYS.'D')))['items'];

        return [
            'next_7_days' => count(array_filter($found, static fn (ApplicantAgendaEntry $entry): bool => $entry->item->startsAt < $soon)),
            'items' => array_map(static fn (ApplicantAgendaEntry $entry): array => [
                'id' => $entry->item->id,
                'type' => $entry->item->type,
                /** A date with no hour (the join date) is the Jakarta day (`Y-m-d`), as in `GET me/agenda`; every other time is ISO 8601. */
                'starts_at' => $entry->item->allDay
                    ? $entry->item->startsAt->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d')
                    : DashboardWindow::iso($entry->item->startsAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')),
                'all_day' => $entry->item->allDay,
                'application_id' => $entry->item->applicationId,
                'job_id' => $entry->item->jobId,
                'job_title' => $entry->jobTitle,
                'company_name' => $entry->companyName,
            ], array_slice($found, 0, self::LIMIT)),
        ];
    }
}
