<?php

namespace Tests\Feature\Identity;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Identity\Interface\Http\Middleware\EnforceTermsAcceptance;
use Tests\FeatureTestCase;
use Tests\Support\RecordingVerificationCodeSender;

final class TermsGateTest extends FeatureTestCase
{
    private const string PASSWORD = 'correct-horse-battery';

    private UserModel $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-03 10:00:00');
        $this->user = UserModel::query()->create([
            'type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'password' => Hash::make(self::PASSWORD),
            'status' => 'active', 'locale' => 'en',
        ]);
        $this->token = $this->user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function publish(string $type, int $version): void
    {
        DB::table('content_legal_documents')->where('type', $type)->where('status', 'published')->update(['status' => 'archived']);
        DB::table('content_legal_documents')->insert([
            'type' => $type, 'version' => $version, 'title' => json_encode(['id' => 'x', 'en' => 'x']), 'body' => json_encode(['id' => 'x', 'en' => 'x']),
            'status' => 'published', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function asUser(string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token)->json($method, "/api/v1/{$uri}", $data);
    }

    public function test_without_any_published_document_nobody_is_gated(): void
    {
        $this->app->instance(VerificationCodeSender::class, new RecordingVerificationCodeSender);

        $this->asUser('POST', 'auth/email/resend')->assertNoContent();
        $this->asUser('GET', 'me/terms-acceptance')->assertOk()->assertExactJson(['data' => ['accepted' => true, 'documents' => []]]);
    }

    public function test_a_user_who_has_not_accepted_is_refused_with_the_documents_to_accept(): void
    {
        $this->publish('terms', 3);
        $this->publish('privacy', 2);

        $response = $this->asUser('POST', 'auth/email/resend')
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'urn:jobportal:error:terms-not-accepted')
            ->assertJsonPath('status', 409);

        $this->assertSame([['type' => 'privacy', 'version' => 2], ['type' => 'terms', 'version' => 3]], $this->sorted($response->json('required_documents')));
    }

    /** @param  list<array{type: string, version: int}>  $documents @return list<array{type: string, version: int}> */
    private function sorted(array $documents): array
    {
        usort($documents, fn (array $a, array $b): int => $a['type'] <=> $b['type']);

        return $documents;
    }

    public function test_the_exempt_routes_stay_reachable(): void
    {
        $this->publish('terms', 1);

        $this->asUser('GET', 'me')->assertOk();
        $this->asUser('GET', 'me/terms-acceptance')->assertOk()->assertJsonPath('data.accepted', false)->assertJsonPath('data.documents.0', ['type' => 'terms', 'version' => 1, 'accepted' => false]);
        $this->asUser('POST', 'me/terms-acceptance', ['terms_version' => 1])->assertNoContent();
        $this->asUser('POST', 'auth/logout')->assertNoContent();
    }

    public function test_the_user_can_still_delete_the_account_without_accepting(): void
    {
        $this->publish('terms', 1);

        $this->asUser('POST', 'me/deletion', ['password' => self::PASSWORD, 'confirm' => true])->assertOk();

        $this->assertSame('deactivated', $this->user->fresh()->status);
    }

    public function test_accepting_records_the_version_language_and_address_and_lifts_the_gate(): void
    {
        $this->publish('terms', 3);
        $this->publish('privacy', 2);
        $this->app->instance(VerificationCodeSender::class, new RecordingVerificationCodeSender);

        $this->asUser('POST', 'me/terms-acceptance', ['terms_version' => 3, 'privacy_version' => 2])->assertNoContent();

        $rows = DB::table('identity_terms_acceptances')->where('user_id', $this->user->id)->orderBy('document_type')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['privacy', 2], [$rows[0]->document_type, (int) $rows[0]->document_version]);
        $this->assertSame(['terms', 3], [$rows[1]->document_type, (int) $rows[1]->document_version]);
        $this->assertSame('127.0.0.1', $rows[0]->ip_address);
        $this->assertSame('2026-10-03 10:00:00', $rows[0]->accepted_at);
        $this->assertSame('en', $rows[0]->locale);

        $this->asUser('POST', 'auth/email/resend')->assertNoContent();
        $this->asUser('GET', 'me/terms-acceptance')->assertJsonPath('data.accepted', true);
    }

    public function test_accepting_twice_keeps_one_record(): void
    {
        $this->publish('terms', 1);

        $this->asUser('POST', 'me/terms-acceptance', ['terms_version' => 1])->assertNoContent();
        $this->asUser('POST', 'me/terms-acceptance', ['terms_version' => 1])->assertNoContent();

        $this->assertSame(1, DB::table('identity_terms_acceptances')->count());
    }

    public function test_an_outdated_version_is_refused_with_the_version_in_force(): void
    {
        $this->publish('terms', 2);

        $this->asUser('POST', 'me/terms-acceptance', ['terms_version' => 1])
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:jobportal:error:legal-document-outdated')
            ->assertJsonPath('required_documents', [['type' => 'terms', 'version' => 2]]);

        $this->assertSame(0, DB::table('identity_terms_acceptances')->count());
    }

    public function test_every_document_in_force_must_be_sent_and_no_other(): void
    {
        $this->publish('terms', 1);
        $this->publish('privacy', 1);

        $this->asUser('POST', 'me/terms-acceptance', ['terms_version' => 1])
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:jobportal:error:legal-document-missing')
            ->assertJsonPath('types', ['privacy']);

        DB::table('content_legal_documents')->where('type', 'privacy')->delete();
        $this->asUser('POST', 'me/terms-acceptance', ['terms_version' => 1, 'privacy_version' => 1])
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:jobportal:error:legal-document-not-active')
            ->assertJsonPath('types', ['privacy']);

        $this->asUser('POST', 'me/terms-acceptance', ['terms_version' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['terms_version'], 'errors');
        $this->assertSame(0, DB::table('identity_terms_acceptances')->count());
    }

    public function test_only_the_documents_in_force_are_required(): void
    {
        $this->publish('terms', 1);
        $this->app->instance(VerificationCodeSender::class, new RecordingVerificationCodeSender);

        $this->asUser('POST', 'me/terms-acceptance', ['terms_version' => 1])->assertNoContent();
        $this->asUser('POST', 'auth/email/resend')->assertNoContent();
    }

    public function test_a_new_version_gates_everyone_again_and_keeps_the_old_acceptance(): void
    {
        $this->publish('terms', 1);
        $this->app->instance(VerificationCodeSender::class, new RecordingVerificationCodeSender);
        $this->asUser('POST', 'me/terms-acceptance', ['terms_version' => 1])->assertNoContent();
        $this->asUser('POST', 'auth/email/resend')->assertNoContent();

        $this->publish('terms', 2);
        Carbon::setTestNow('2026-10-03 10:05:00');

        $this->asUser('POST', 'auth/email/resend')->assertStatus(409)->assertJsonPath('required_documents', [['type' => 'terms', 'version' => 2]]);
        $this->asUser('POST', 'me/terms-acceptance', ['terms_version' => 2])->assertNoContent();
        $this->asUser('POST', 'auth/email/resend')->assertNoContent();

        $this->assertSame([1, 2], DB::table('identity_terms_acceptances')->orderBy('document_version')->pluck('document_version')->map(fn ($v) => (int) $v)->all());
    }

    public function test_public_routes_are_never_gated_even_when_a_token_is_sent(): void
    {
        $this->publish('terms', 1);

        $this->asUser('POST', 'auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])->assertOk();
        $this->asUser('GET', 'legal-documents/terms')->assertOk();
        $this->asUser('GET', 'faq')->assertOk();
        $this->asUser('POST', 'auth/password/forgot', ['email' => 'siti@example.test'])->assertNoContent();
    }

    public function test_admins_are_not_concerned(): void
    {
        $this->publish('terms', 1);
        $admin = AdminModel::query()->create(['name' => 'Root', 'email' => 'root@example.test', 'password' => 'hash', 'status' => 'active']);

        $this->withToken($admin->createToken('console', ['*'], now()->addHour())->plainTextToken)->getJson('/api/v1/admin/me')->assertOk();
    }

    public function test_a_missing_or_invalid_token_is_a_401_not_a_409(): void
    {
        $this->publish('terms', 1);

        $this->postJson('/api/v1/auth/email/resend')->assertStatus(401);
        $this->withToken('999|not-a-real-token')->postJson('/api/v1/auth/email/resend')->assertStatus(401);
    }

    /** The resolved middleware of a route (groups expanded, exclusions applied): what really runs. */
    private function resolvedMiddleware(RoutingRoute $route): array
    {
        return $this->app->make('router')->gatherRouteMiddleware($route);
    }

    public function test_exactly_the_documented_routes_that_need_a_user_are_exempt_from_the_gate(): void
    {
        $exempt = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => in_array(EnforceTermsAcceptance::USER_AUTH_MIDDLEWARE, $route->gatherMiddleware(), true))
            ->reject(fn (RoutingRoute $route): bool => in_array(EnforceTermsAcceptance::class, $this->resolvedMiddleware($route), true))
            ->map(fn (RoutingRoute $route): string => implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'GET api/v1/me',
            'GET api/v1/me/terms-acceptance',
            'POST api/v1/auth/logout',
            'POST api/v1/auth/logout-all',
            'POST api/v1/me/deletion',
            'POST api/v1/me/terms-acceptance',
        ], $exempt);
    }

    public function test_no_public_or_admin_route_runs_the_gate_for_nothing(): void
    {
        $gatedWithoutUser = collect(Route::getRoutes()->getRoutes())
            ->reject(fn (RoutingRoute $route): bool => in_array(EnforceTermsAcceptance::USER_AUTH_MIDDLEWARE, $route->gatherMiddleware(), true))
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1/'))
            ->filter(fn (RoutingRoute $route): bool => ! in_array(EnforceTermsAcceptance::class, $this->resolvedMiddleware($route), true))
            ->all();

        // The gate sits in the `api` group, so it is present everywhere and decides by itself; this documents that nothing opts out by accident.
        $this->assertSame([], $gatedWithoutUser);
    }
}
