<?php

namespace Tests\Feature\Identity;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Tests\FeatureTestCase;

final class TokenHygieneTest extends FeatureTestCase
{
    public function test_expired_tokens_are_pruned_nightly_without_overlap(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'sanctum:prune-expired'));

        $this->assertCount(1, $events);
        $this->assertStringContainsString('--hours=24', $events->first()->command);
        $this->assertSame('30 2 * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
        $this->assertTrue($events->first()->onOneServer);
    }

    public function test_the_csrf_cookie_route_does_not_exist(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('sanctum.csrf-cookie'));
        $this->get('/sanctum/csrf-cookie')->assertNotFound();
    }

    public function test_no_route_uses_the_web_group_except_the_documentation(): void
    {
        $web = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('web', $route->gatherMiddleware(), true))
            ->reject(fn ($route) => str_starts_with($route->uri(), 'docs/') || str_starts_with($route->uri(), '_scramble/'))
            ->map(fn ($route) => $route->uri());

        $this->assertSame([], $web->values()->all());
    }
}
