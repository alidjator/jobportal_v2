<?php

namespace Src\Job\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Company\Domain\Events\CompanyNameChangeDecided;
use Src\Company\Domain\Events\CompanyRenamed;
use Src\Job\Infrastructure\Persistence\JobSearchVectors;
use Src\Shared\Infrastructure\Queue\QueueName;

/** The company name is part of the search vector of its jobs, so a new name refreshes them all. Runs on the `bulk` queue; idempotent. */
final class RefreshSearchVectorsOfCompany implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(private readonly JobSearchVectors $vectors) {}

    public function viaQueue(): string
    {
        return QueueName::Bulk->value;
    }

    public function viaConnection(): ?string
    {
        return QueueName::Bulk->connection();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function onRenamed(CompanyRenamed $event): void
    {
        $this->vectors->refreshCompany($event->companyId);
    }

    public function onNameChangeDecided(CompanyNameChangeDecided $event): void
    {
        if ($event->decision === CompanyNameChangeDecided::APPROVED) {
            $this->vectors->refreshCompany($event->companyId);
        }
    }
}
