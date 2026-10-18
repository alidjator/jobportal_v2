<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Auth\SendImportedPasswordResets;

/** Sends the password reset email to the imported accounts that have no password. A dry run (the default) only reports; `--write` sends, at most `--limit` emails per run. */
final class SendImportedPasswordResetsCommand extends Command
{
    protected $signature = 'identity:send-imported-password-resets
        {--write : Send the emails (without it the run only reports what it would do)}
        {--limit=200 : The most emails to send in this run}
        {--delay=1 : Seconds to wait after each email}';

    protected $description = 'Email a password reset code to the accounts without a password (imported from karir.pkp); dry run unless --write';

    public function handle(SendImportedPasswordResets $send): int
    {
        $limit = (int) $this->option('limit');
        $delay = max(0, (int) $this->option('delay'));

        if ($limit < 1) {
            $this->error('The limit must be at least 1.');

            return self::FAILURE;
        }

        $write = (bool) $this->option('write');

        if ($write && ! $this->confirm("Send up to {$limit} password reset emails?")) {
            return self::FAILURE;
        }

        $report = $send->execute($write, $limit, $delay > 0 ? function () use ($delay): void {
            sleep($delay);
        } : null);

        $this->table(['Count', 'Accounts'], [
            ['Without a password', $report->withoutPassword],
            ['Email address suppressed (skipped)', $report->suppressed],
            ['Already issued a reset code (skipped)', $report->alreadyIssued],
            ['Left to send', $report->sendable],
            [$write ? 'Sent in this run' : 'Would send in this run', $write ? $report->sent : min($limit, $report->sendable)],
        ]);

        if (! $write) {
            $this->info('Dry run: nothing was sent. Add --write to send.');
        }

        return self::SUCCESS;
    }
}
