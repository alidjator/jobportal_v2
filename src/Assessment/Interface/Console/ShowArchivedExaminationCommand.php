<?php

namespace Src\Assessment\Interface\Console;

use Illuminate\Console\Command;
use Src\Assessment\Application\Archive\ShowArchivedExamination;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class ShowArchivedExaminationCommand extends Command
{
    protected $signature = 'assessment:archive:show {report_number : The number of the report} {reference : The reference of the documented legal request, written to the audit log}';

    protected $description = 'Print the archived raw data of an erased psychological examination for a documented legal request (audited)';

    public function handle(ShowArchivedExamination $show): int
    {
        try {
            $found = $show->execute((string) $this->argument('report_number'), (string) $this->argument('reference'));
        } catch (EntityNotFound) {
            $this->error('Nothing is archived under this report number.');

            return self::FAILURE;
        }

        foreach ($found as $examination) {
            $this->line(json_encode([
                'attempt_id' => $examination->attemptId,
                'provider_id' => $examination->providerId,
                'report_number' => $examination->reportNumber,
                'archived_at' => $examination->archivedAt->format(DATE_ATOM),
                'retain_until' => $examination->retainUntil->format('Y-m-d'),
                'files' => array_map(static fn (array $file): array => ['kind' => $file['kind'], 'name' => $file['name'], 'path' => $file['path'], 'sha256' => $file['sha256']], $examination->files),
                'payload' => $examination->payload,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        return self::SUCCESS;
    }
}
