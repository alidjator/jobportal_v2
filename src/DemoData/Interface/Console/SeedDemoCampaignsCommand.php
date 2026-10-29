<?php

namespace Src\DemoData\Interface\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Src\DemoData\Application\DemoCampaigns;
use Src\DemoData\Infrastructure\DemoDataServiceProvider;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * Adds the demo campaigns (eight of the platform and of the two companies, in every state) and the job seekers who signed up through the running ones to the demo group that `demo:seed` made. It skips a campaign
 * code and an email that exist, so it can run again. The sign-ups get one random password, printed once. Refused in production.
 */
final class SeedDemoCampaignsCommand extends Command
{
    private const int PASSWORD_LENGTH = 16;

    protected $signature = 'demo:seed-campaigns';

    protected $description = 'Add the demo campaigns and the job seekers who signed up through them (refused in production)';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('The demo data is for development databases only: demo:seed-campaigns is refused in production.');

            return self::FAILURE;
        }

        DemoDataServiceProvider::useDemoAdapters($this->laravel);
        $password = Str::password(self::PASSWORD_LENGTH, symbols: false);

        try {
            $campaigns = $this->laravel->make(DemoCampaigns::class);
            $made = $campaigns->campaigns($campaigns->admin(), $campaigns->companyIds());
            $accounts = $campaigns->registrants($password);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Demo campaigns written.');
        $this->table(['What', 'Made in this run'], [['campaigns', $made], ['job seeker accounts', $accounts]]);

        if ($accounts > 0) {
            $this->line("Password of the new job seeker accounts (shown once): {$password}");
        }

        return self::SUCCESS;
    }
}
