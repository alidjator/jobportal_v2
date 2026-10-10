<?php

namespace Src\Training\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Src\Shared\Infrastructure\ContextServiceProvider;
use Src\Training\Application\TrainingCatalog;

final class TrainingServiceProvider extends ContextServiceProvider
{
    /** The name of the route limiter of the public course list: 60 requests per client address per minute. */
    public const string PUBLIC_LIMITER = 'training-public';

    private const int PUBLIC_PER_MINUTE = 60;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        TrainingCatalog::class => CachedTrainingCatalog::class,
    ];

    public function register(): void
    {
        $this->app->bind(MoodleTrainingCatalog::class, fn ($app): MoodleTrainingCatalog => new MoodleTrainingCatalog(
            $app->make(Factory::class),
            $this->setting($app->make(Repository::class), 'url'),
            $this->setting($app->make(Repository::class), 'token'),
            $this->setting($app->make(Repository::class), 'course_url'),
        ));
    }

    public function boot(): void
    {
        RateLimiter::for(self::PUBLIC_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::PUBLIC_PER_MINUTE)->by('training-public|'.$request->ip()));

        parent::boot();
    }

    private function setting(Repository $config, string $key): ?string
    {
        $value = $config->get('services.moodle.'.$key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
