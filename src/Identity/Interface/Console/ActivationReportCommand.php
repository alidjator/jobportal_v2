<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Activation\GetActivationProgress;
use Src\Identity\Domain\UserType;
use Src\Shared\Application\Actor;

/** Prints the activation report of `GET admin/activation/report` (`docs/design/phase-8-activation.md` section 10) for one audience or both. */
final class ActivationReportCommand extends Command
{
    protected $signature = 'identity:activation-report {--audience= : company or job_seeker (both when empty)}';

    protected $description = 'Print how far the activation of the imported karir.pkp accounts has come';

    public function handle(GetActivationProgress $progress): int
    {
        $audiences = match ($this->option('audience')) {
            'company' => [UserType::CompanyMember],
            'job_seeker' => [UserType::JobSeeker],
            null, '' => [UserType::JobSeeker, UserType::CompanyMember],
            default => null,
        };

        if ($audiences === null) {
            $this->error('Give --audience=company or --audience=job_seeker, or none for both.');

            return self::FAILURE;
        }

        foreach ($audiences as $audience) {
            $report = $progress->execute(Actor::system(), $audience);

            $this->line($audience === UserType::CompanyMember ? 'Company owners' : 'Job seekers');
            $this->table(['Imported', 'Deactivated', 'Unverified', 'Suppressed', 'Eligible'], [[$report['imported'], $report['deactivated'], $report['unverified'], $report['suppressed'], $report['eligible']]]);
            $this->table(['First email', 'Sent', 'Reminders', 'Bounced', 'Complaints', 'Signed in', '% of sent', '% of imported'], [[
                $report['activation']['first_sent_at'] ?? '-', $report['activation']['sent'], $report['activation']['reminders_sent'], $report['activation']['bounced'], $report['activation']['complaints'],
                $report['signed_in']['count'], $this->percent($report['signed_in']['percent_of_sent']), $this->percent($report['signed_in']['percent_of_imported']),
            ]]);

            if ($report['milestones'] !== []) {
                $this->table(['Day', 'Date', 'Reached', 'Signed in', '% of sent'], array_map(fn (array $mark): array => [
                    $mark['day'], $mark['date'], $mark['reached'] ? 'yes' : 'no', $mark['signed_in'] ?? '-', $this->percent($mark['percent_of_sent']),
                ], $report['milestones']));
            }

            if ($report['batches'] !== []) {
                $this->table(['Batch', 'Sent at', 'Sent', 'Bounced', 'Complaints', 'Signed in'], array_map(static fn (array $batch): array => [
                    $batch['batch'], $batch['sent_at'], $batch['sent'], $batch['bounced'], $batch['complaints'], $batch['signed_in'],
                ], $report['batches']));
            }

            if (isset($report['companies_not_signed_in'])) {
                $this->line('Companies not signed in yet: '.count($report['companies_not_signed_in']));

                foreach ($report['companies_not_signed_in'] as $company) {
                    $this->line('  - '.$company['name']);
                }
            }
        }

        return self::SUCCESS;
    }

    private function percent(?float $value): string
    {
        return $value === null ? '-' : "{$value}%";
    }
}
