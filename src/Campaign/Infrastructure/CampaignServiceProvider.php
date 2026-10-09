<?php

namespace Src\Campaign\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Src\Campaign\Application\CampaignLinks;
use Src\Campaign\Application\CampaignPermissions;
use Src\Campaign\Application\CampaignStatistics;
use Src\Campaign\Application\QrCodes;
use Src\Campaign\Application\RunningCampaignCodes;
use Src\Campaign\Domain\CampaignRepository;
use Src\Campaign\Infrastructure\Persistence\DatabaseCampaignRepository;
use Src\Campaign\Infrastructure\Persistence\DatabaseCampaignStatistics;
use Src\Shared\Application\Campaign\CampaignCodes;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class CampaignServiceProvider extends ContextServiceProvider
{
    /** The name of the route limiter of the public campaign page: 60 requests per client address per minute. */
    public const string PUBLIC_LIMITER = 'campaign-public';

    private const int PUBLIC_PER_MINUTE = 60;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        CampaignRepository::class => DatabaseCampaignRepository::class,
        CampaignStatistics::class => DatabaseCampaignStatistics::class,
        CampaignLinks::class => ConfiguredCampaignLinks::class,
        QrCodes::class => BaconQrCodes::class,
        CampaignCodes::class => RunningCampaignCodes::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...CampaignPermissions::definitions());
        });
    }

    public function boot(): void
    {
        RateLimiter::for(self::PUBLIC_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::PUBLIC_PER_MINUTE)->by('campaign-public|'.$request->ip()));

        parent::boot();
    }
}
