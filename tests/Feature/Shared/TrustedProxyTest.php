<?php

namespace Tests\Feature\Shared;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\FeatureTestCase;

/** `docs/design/frontend.md` 13.1 point 2 and the guest answer: the loopback proxy is trusted for `X-Forwarded-For` only, and a guest always gets the 401 problem. */
final class TrustedProxyTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/probe')->get('ip', fn (Request $request) => ['ip' => $request->ip(), 'host' => $request->getHost(), 'secure' => $request->isSecure()]);
    }

    public function test_the_visitor_address_is_read_from_a_loopback_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson('/api/v1/probe/ip', ['X-Forwarded-For' => '203.0.113.7'])
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.7');
    }

    public function test_the_header_is_ignored_from_any_other_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->getJson('/api/v1/probe/ip', ['X-Forwarded-For' => '203.0.113.7'])
            ->assertOk()
            ->assertJsonPath('ip', '198.51.100.9');
    }

    public function test_other_forwarded_headers_are_not_trusted(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson('/api/v1/probe/ip', ['X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Proto' => 'https'])
            ->assertOk()
            ->assertJsonPath('ip', '127.0.0.1')
            ->assertJsonPath('secure', false)
            ->assertJsonMissing(['host' => 'evil.example']);
    }

    public function test_a_guest_without_a_json_accept_header_gets_the_401_problem(): void
    {
        $this->call('GET', '/api/v1/company/me', server: ['HTTP_ACCEPT' => '*/*'])
            ->assertUnauthorized()
            ->assertJsonPath('type', 'urn:onesis:error:unauthenticated');
    }
}
