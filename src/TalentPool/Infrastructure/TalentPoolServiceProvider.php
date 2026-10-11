<?php

namespace Src\TalentPool\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Infrastructure\ContextServiceProvider;
use Src\TalentPool\Application\EraseTalentEntries;
use Src\TalentPool\Domain\CategoryRepository;
use Src\TalentPool\Domain\EntryRepository;
use Src\TalentPool\Infrastructure\Listeners\EraseTalentEntriesWhenUserAnonymized;
use Src\TalentPool\Infrastructure\Persistence\DatabaseCategoryRepository;
use Src\TalentPool\Infrastructure\Persistence\DatabaseEntryRepository;
use Src\TalentPool\Interface\Console\ClearHiddenNotesCommand;

final class TalentPoolServiceProvider extends ContextServiceProvider
{
    /** The name of the route limiter of the writes (save, move, remove, categories): 120 requests per account per minute. */
    public const string WRITE_LIMITER = 'talent-pool-write';

    private const int WRITES_PER_MINUTE = 120;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        CategoryRepository::class => DatabaseCategoryRepository::class,
        EntryRepository::class => DatabaseEntryRepository::class,
    ];

    public function register(): void
    {
        $this->commands([ClearHiddenNotesCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Once a night, Jakarta time: the notes of candidates who can no longer be found are deleted.
            $schedule->command(ClearHiddenNotesCommand::class)->dailyAt('04:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
        });
    }

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for(self::WRITE_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::WRITES_PER_MINUTE)->by('talent-pool-write|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        $this->app->tag(EraseTalentEntries::class, ErasureStep::class);

        Event::listen(UserAnonymized::class, EraseTalentEntriesWhenUserAnonymized::class);
    }
}
