<?php

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Route;
use Tests\FeatureTestCase;

final class CorsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/probe')->get('cors', fn () => ['ok' => true]);
    }

    public function test_a_configured_origin_is_allowed_without_credentials(): void
    {
        config(['cors.allowed_origins' => ['https://karir.example']]);

        $this->getJson('/api/v1/probe/cors', ['Origin' => 'https://karir.example'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://karir.example')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_another_origin_is_never_echoed_back(): void
    {
        config(['cors.allowed_origins' => ['https://karir.example']]);

        // With one allowed origin the header names that origin to every caller; the browser refuses the answer for any other page.
        $this->getJson('/api/v1/probe/cors', ['Origin' => 'https://evil.example'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://karir.example');
    }

    public function test_no_origin_is_allowed_by_default(): void
    {
        $this->assertSame([], (include base_path('config/cors.php'))['allowed_origins']);
        $this->assertFalse((include base_path('config/cors.php'))['supports_credentials']);
        $this->assertSame(['api/*'], config('cors.paths'));
    }
}
