<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Activation\ActivationRun;
use Src\Identity\Application\Activation\SendActivationEmails;
use Src\Identity\Domain\UserType;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Emails the imported accounts that have not signed in how to get in (`docs/design/phase-8-activation.md` section 3). A dry run (the default) only reports; `--write` sends, at most
 * `--limit` emails per run. `--emails=<file>` limits the run to the addresses in the file (one per line): the staff test before the first batch.
 */
final class SendActivationEmailsCommand extends Command
{
    protected $signature = 'identity:send-activation-emails
        {--audience= : company or job_seeker}
        {--write : Send the emails (without it the run only reports what it would do)}
        {--limit=200 : The most emails to send in this run}
        {--delay=1 : Seconds to wait after each email}
        {--pilot : Label the batch as the pilot}
        {--reminders : Send the single reminder instead of the activation email}
        {--emails= : A file with one address per line; only these accounts (staff test, logged as manual)}';

    protected $description = 'Email the imported karir.pkp accounts how to sign in (activation email or reminder); dry run unless --write';

    public function handle(SendActivationEmails $send): int
    {
        $audience = match ($this->option('audience')) {
            'company' => UserType::CompanyMember,
            'job_seeker' => UserType::JobSeeker,
            default => null,
        };
        $limit = (int) $this->option('limit');
        $delay = max(0, (int) $this->option('delay'));

        if ($audience === null) {
            $this->error('Give --audience=company or --audience=job_seeker.');

            return self::FAILURE;
        }

        if ($limit < 1) {
            $this->error('The limit must be at least 1.');

            return self::FAILURE;
        }

        $emails = null;

        if ($this->option('emails') !== null) {
            $emails = $this->addressesIn((string) $this->option('emails'));

            if ($emails === null) {
                $this->error('The file with the addresses could not be read or is empty.');

                return self::FAILURE;
            }
        }

        $write = (bool) $this->option('write');
        $what = $this->option('reminders') ? 'reminder' : 'activation';

        if ($write && ! $this->confirm("Send up to {$limit} {$what} emails?")) {
            return self::FAILURE;
        }

        try {
            $report = $send->execute(
                new ActivationRun($audience, $write, $limit, (bool) $this->option('reminders'), (bool) $this->option('pilot'), $emails),
                $delay > 0 ? function () use ($delay): void {
                    sleep($delay);
                } : null,
            );
        } catch (BusinessRuleViolation $violation) {
            $this->error($violation->getMessage());

            return self::FAILURE;
        }

        $rows = [
            ['Batch', $report->batch],
            ['Not signed in yet (imported)', $report->eligible],
            ['Email address suppressed (skipped)', $report->suppressed],
            ['Deactivated or suspended (skipped)', $report->deactivated],
            ['Already got this email (skipped)', $report->alreadySent],
            ['Not due yet or no activation email (reminders)', $report->notDue],
            ['Unverified address, held back until the verified ones are done', $report->unverifiedHeldBack],
            ['Left to send', $report->sendable],
            [$write ? 'Sent in this run' : 'Would send in this run', $write ? $report->sent : min($limit, $report->sendable)],
        ];

        if ($report->signedIn !== null) {
            $rows[] = ['Company owners that have signed in', $report->signedIn];
        }

        $this->table(['Count', 'Accounts'], $rows);

        foreach ($report->blockedBy as $reason) {
            $this->warn("A real run would be refused: {$reason}");
        }

        if (! $write) {
            $this->info('Dry run: nothing was sent. Add --write to send.');
        }

        return self::SUCCESS;
    }

    /** @return list<string>|null */
    private function addressesIn(string $path): ?array
    {
        $lines = is_file($path) && is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;

        if ($lines === false) {
            return null;
        }

        $emails = array_values(array_unique(array_filter(array_map(static fn (string $line): string => mb_strtolower(trim($line)), $lines), static fn (string $line): bool => $line !== '')));

        return $emails === [] ? null : $emails;
    }
}
