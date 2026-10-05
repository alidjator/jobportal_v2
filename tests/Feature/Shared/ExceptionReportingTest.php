<?php

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Tests\FeatureTestCase;

/**
 * A client error is an answer, not a fault: logging it with a stack trace would let anyone fill the disk by guessing tokens or failing
 * sign-ins. Real faults, and an unavailable external service, must still be logged.
 */
final class ExceptionReportingTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/v1/_test/fail/{kind}', function (string $kind): never {
            throw match ($kind) {
                'not-found' => new EntityNotFound('Thing not found.', 'thing-not-found'),
                'denied' => new AccessDenied('Not allowed.', 'not-allowed'),
                'conflict' => new StateConflict('Already there.', 'already-there'),
                'rule' => new BusinessRuleViolation('Rule broken.', 'rule-broken'),
                'throttled' => new TooManyAttempts('Slow down.', 30),
                'external' => new ExternalServiceUnavailable('Service is off.', 'service-off'),
                default => new RuntimeException('Something broke.'),
            };
        });
    }

    /** @return array<string, array{string, int}> */
    public static function clientErrors(): array
    {
        return [
            'not found' => ['not-found', 404],
            'access denied' => ['denied', 403],
            'state conflict' => ['conflict', 409],
            'business rule' => ['rule', 422],
            'too many attempts' => ['throttled', 429],
        ];
    }

    #[DataProvider('clientErrors')]
    public function test_a_client_error_is_answered_but_not_logged(string $kind, int $status): void
    {
        Log::spy();

        $this->getJson("/api/v1/_test/fail/{$kind}")->assertStatus($status);

        Log::shouldNotHaveReceived('error');
    }

    public function test_real_application_routes_do_not_log_their_client_errors(): void
    {
        Log::spy();

        $this->getJson('/api/v1/auth/invitations/'.str_repeat('a', 43))->assertStatus(404);
        $this->getJson('/api/v1/companies/no-such-company')->assertStatus(404);
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password'])->assertStatus(401);
        $this->getJson('/api/v1/me')->assertStatus(401);

        Log::shouldNotHaveReceived('error');
    }

    public function test_an_unexpected_exception_is_still_logged_and_answered_without_details(): void
    {
        Log::spy();

        $response = $this->getJson('/api/v1/_test/fail/bug')->assertStatus(500);

        $this->assertStringNotContainsString('Something broke', (string) $response->getContent());
        Log::shouldHaveReceived('error')->once();
    }

    public function test_an_unavailable_external_service_is_still_logged(): void
    {
        Log::spy();

        $this->getJson('/api/v1/_test/fail/external')->assertStatus(503);

        Log::shouldHaveReceived('error')->once();
    }
}
