<?php

namespace Src\JobAlert\Application\Export;

use DateTimeInterface;
use Src\JobAlert\Domain\JobAlert;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;

/** The JobAlert part of the personal data export: the alerts the job seeker made, with their search filters and how they are told. */
final readonly class AlertsDataExporter implements PersonalDataExporter
{
    public function __construct(private JobAlertRepository $alerts) {}

    public function key(): string
    {
        return 'job_alerts';
    }

    public function export(int $userId): PersonalDataSection
    {
        return new PersonalDataSection(array_map(static fn (JobAlert $alert): array => [
            'name' => $alert->name,
            'frequency' => $alert->frequency->value,
            'filters' => $alert->criteria->toArray(),
            'by_email' => $alert->email,
            'in_app' => $alert->inApp,
            'paused' => $alert->paused,
            'created_at' => $alert->createdAt->format(DateTimeInterface::ATOM),
        ], $this->alerts->ofUser($userId)));
    }
}
