<?php

namespace Src\TalentPool\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Shared\Infrastructure\ContextServiceProvider;
use Src\TalentPool\Domain\CategoryRepository;
use Src\TalentPool\Domain\EntryRepository;
use Src\TalentPool\Infrastructure\Listeners\EraseTalentEntriesWhenUserAnonymized;
use Src\TalentPool\Infrastructure\Persistence\DatabaseCategoryRepository;
use Src\TalentPool\Infrastructure\Persistence\DatabaseEntryRepository;

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

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for(self::WRITE_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::WRITES_PER_MINUTE)->by('talent-pool-write|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        Event::listen(UserAnonymized::class, EraseTalentEntriesWhenUserAnonymized::class);
    }
}
