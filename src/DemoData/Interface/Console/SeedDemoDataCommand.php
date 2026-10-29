<?php

namespace Src\DemoData\Interface\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Src\DemoData\Application\DemoAccount;
use Src\DemoData\Application\SeedDemoData;
use Src\DemoData\Infrastructure\DemoDataServiceProvider;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * Fills a development database with fictitious demo data. Refused in production. One random password is made for every demo account and printed once
 * at the end; it is not written to a file, a log or the database (only the accounts' hashes are stored).
 */
final class SeedDemoDataCommand extends Command
{
    private const int PASSWORD_LENGTH = 20;

    protected $signature = 'demo:seed {--full-batteries : Also write the full-size psychological batteries (about 3,300 questions; adds several minutes)}';

    protected $description = 'Fill a development database with fictitious demo accounts, companies, jobs and applications (refused in production)';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('The demo data is for development databases only: demo:seed is refused in production.');

            return self::FAILURE;
        }

        DemoDataServiceProvider::useDemoAdapters($this->laravel);
        $password = Str::password(self::PASSWORD_LENGTH, symbols: false);

        try {
            $accounts = $this->laravel->make(SeedDemoData::class)->execute($password, (bool) $this->option('full-batteries'));
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Demo data created.');
        $this->table(
            ['Application', 'Role', 'Name', 'Email'],
            array_map(static fn (DemoAccount $account): array => [$account->application, $account->role, $account->name, $account->email], $accounts),
        );
        $this->newLine();
        $this->line("Password of every demo account: <comment>{$password}</comment>");
        $this->warn('It is shown only now and stored nowhere: keep it in a password manager. The platform admin and every Workspace account (the group asks for it) enrol a second factor at the first sign-in.');

        return self::SUCCESS;
    }
}
