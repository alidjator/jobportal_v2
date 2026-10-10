<?php

namespace Src\Review\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Review\Application\ReviewPermissions;
use Src\Review\Application\ReviewSummaries;
use Src\Review\Domain\ReplyRepository;
use Src\Review\Domain\ReportRepository;
use Src\Review\Domain\ReviewRepository;
use Src\Review\Infrastructure\Listeners\EraseReviewerDataWhenUserAnonymized;
use Src\Review\Infrastructure\Persistence\DatabaseReplyRepository;
use Src\Review\Infrastructure\Persistence\DatabaseReportRepository;
use Src\Review\Infrastructure\Persistence\DatabaseReviewRepository;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class ReviewServiceProvider extends ContextServiceProvider
{
    /** Route limiters: the public list per client address, the writes of a job seeker and of a company per account. */
    public const string PUBLIC_LIMITER = 'review-public';

    public const string WRITE_LIMITER = 'review-write';

    public const string COMPANY_WRITE_LIMITER = 'review-company-write';

    private const int PUBLIC_PER_MINUTE = 60;

    private const int WRITES_PER_HOUR = 10;

    private const int COMPANY_WRITES_PER_HOUR = 30;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        ReviewRepository::class => DatabaseReviewRepository::class,
        ReplyRepository::class => DatabaseReplyRepository::class,
        ReportRepository::class => DatabaseReportRepository::class,
        ReviewSummaries::class => CachedReviewSummaries::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...ReviewPermissions::definitions());
        });
    }

    public function boot(): void
    {
        RateLimiter::for(self::PUBLIC_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::PUBLIC_PER_MINUTE)->by('review-public|'.$request->ip()));
        RateLimiter::for(self::WRITE_LIMITER, fn (Request $request): Limit => Limit::perHour(self::WRITES_PER_HOUR)->by('review-write|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for(self::COMPANY_WRITE_LIMITER, fn (Request $request): Limit => Limit::perHour(self::COMPANY_WRITES_PER_HOUR)->by('review-company-write|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        Event::listen(UserAnonymized::class, EraseReviewerDataWhenUserAnonymized::class);

        parent::boot();
    }
}
