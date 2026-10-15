<?php

namespace Tests\Feature\Shared;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Infrastructure\ContextServiceProvider;
use Tests\FeatureTestCase;

final class ProblemDetailsTest extends FeatureTestCase
{
    private const string PREFIX = ContextServiceProvider::API_PREFIX;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix(self::PREFIX.'/probe')->group(function (): void {
            Route::get('not-found', fn () => throw EntityNotFound::of('Job', 7));
            Route::get('forbidden', fn () => throw new AccessDenied('Not your job.'));
            Route::get('rule', fn () => throw new BusinessRuleViolation('Daily limit reached.', 'invitation-limit-reached'));
            Route::get('conflict', fn () => throw new StateConflict('Already applied.', 'already-applied'));
            Route::get('unauthenticated', fn () => throw new AuthenticationException);
            Route::get('validation', fn () => throw ValidationException::withMessages(['email' => ['The email is required.']]));
            Route::get('crash', fn () => throw new RuntimeException('secret internals'));
        });
    }

    public function test_an_unknown_route_is_a_404_problem(): void
    {
        $this->getJson(self::PREFIX.'/nothing-here')
            ->assertStatus(404)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['type' => 'urn:onesis:error:not-found', 'status' => 404]);
    }

    public function test_a_wrong_method_is_a_405_problem(): void
    {
        $this->postJson(self::PREFIX.'/probe/not-found')
            ->assertStatus(405)
            ->assertJson(['type' => 'urn:onesis:error:method-not-allowed']);
    }

    public function test_an_entity_not_found_is_a_404_problem_with_the_default_code(): void
    {
        $this->getJson(self::PREFIX.'/probe/not-found')
            ->assertStatus(404)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertExactJson([
                'type' => 'urn:onesis:error:not-found',
                'title' => 'Not Found',
                'status' => 404,
                'detail' => 'Job not found: 7',
            ]);
    }

    public function test_access_denied_is_a_403_problem(): void
    {
        $this->getJson(self::PREFIX.'/probe/forbidden')
            ->assertStatus(403)
            ->assertJson(['type' => 'urn:onesis:error:forbidden', 'detail' => 'Not your job.']);
    }

    public function test_a_business_rule_violation_is_a_422_problem_with_its_own_code(): void
    {
        $this->getJson(self::PREFIX.'/probe/rule')
            ->assertStatus(422)
            ->assertJson(['type' => 'urn:onesis:error:invitation-limit-reached', 'detail' => 'Daily limit reached.']);
    }

    public function test_a_state_conflict_is_a_409_problem_with_its_own_code(): void
    {
        $this->getJson(self::PREFIX.'/probe/conflict')
            ->assertStatus(409)
            ->assertJson(['type' => 'urn:onesis:error:already-applied', 'title' => 'Conflict']);
    }

    public function test_an_unauthenticated_request_is_a_401_problem(): void
    {
        $this->getJson(self::PREFIX.'/probe/unauthenticated')
            ->assertStatus(401)
            ->assertJson(['type' => 'urn:onesis:error:unauthenticated']);
    }

    public function test_a_validation_failure_is_a_422_problem_with_errors(): void
    {
        $this->getJson(self::PREFIX.'/probe/validation')
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'type' => 'urn:onesis:error:validation-failed',
                'errors' => ['email' => ['The email is required.']],
            ]);
    }

    public function test_an_unexpected_error_is_a_500_problem_that_hides_internals(): void
    {
        $response = $this->getJson(self::PREFIX.'/probe/crash')
            ->assertStatus(500)
            ->assertJson(['type' => 'urn:onesis:error:internal-error']);

        $this->assertStringNotContainsString('secret internals', $response->getContent());
    }
}
