<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Src\Shared\Application\RequestContext;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Tests\FeatureTestCase;

class SharedKernelTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api')->group(function () {
            Route::get('/_t/ctx', fn () => response()->json((array) app(RequestContext::class)));
            Route::get('/_t/missing', fn () => throw EntityNotFound::of('Job', 5));
            Route::get('/_t/rule', fn () => throw new BusinessRuleViolation('nope'));
            Route::get('/_t/auth', fn () => 'ok')->middleware('auth:sanctum');
            Route::get('/_t/company', fn () => 'ok')->middleware(['auth:sanctum', 'api_company']);
        });
    }

    public function test_unknown_api_route_returns_error_envelope(): void
    {
        $this->getJson('/api/nope')->assertNotFound()->assertExactJson(['error' => 'Not Found']);
    }

    public function test_domain_exceptions_are_mapped(): void
    {
        $this->getJson('/api/_t/missing')->assertNotFound()->assertJson(['error' => 'Job not found: 5']);
        $this->getJson('/api/_t/rule')->assertStatus(400)->assertExactJson(['error' => 'nope']);
    }

    public function test_unauthenticated_uses_error_envelope(): void
    {
        $this->getJson('/api/_t/auth')->assertUnauthorized()->assertExactJson(['error' => 'Unauthenticated']);
    }

    public function test_request_context_is_resolved_from_headers(): void
    {
        $this->getJson('/api/_t/ctx', ['X-Language' => 'ID', 'selected_country' => '101', 'X-Currency' => 'idr'])
            ->assertExactJson(['language' => 'id', 'countryId' => 101, 'currency' => 'IDR']);
    }

    public function test_accept_language_is_used_as_fallback(): void
    {
        $this->getJson('/api/_t/ctx', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertJson(['language' => 'en-us']);
    }
}
