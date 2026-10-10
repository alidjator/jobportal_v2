<?php

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Route;
use Tests\FeatureTestCase;

final class SecurityHeadersTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/probe')->group(function (): void {
            Route::get('json', fn () => ['ok' => true]);
            Route::get('missing', fn () => abort(404));
            Route::get('download', fn () => response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf', 'Content-Security-Policy' => "default-src 'none'; sandbox"]));
        });
    }

    public function test_a_json_answer_carries_every_security_header(): void
    {
        $this->getJson('/api/v1/probe/json')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    public function test_an_error_answer_carries_them_too(): void
    {
        $this->getJson('/api/v1/probe/missing')
            ->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    public function test_a_response_keeps_a_header_it_already_sets(): void
    {
        $this->get('/api/v1/probe/download')
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_non_json_answer_gets_no_content_security_policy_of_its_own(): void
    {
        Route::middleware('api')->prefix('api/v1/probe')->get('text', fn () => response('hello', 200, ['Content-Type' => 'text/plain']));

        $this->get('/api/v1/probe/text')->assertOk()->assertHeaderMissing('Content-Security-Policy');
    }
}
