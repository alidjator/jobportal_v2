<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\FeatureTestCase;

/** Phase 6, part 2: partner providers, psychologist invitations and the admin's control of the psychologists. */
final class ProviderAdminTest extends FeatureTestCase
{
    private const string LINK = 'https://careers.test/provider/invitations/{token}';

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 09:00:00');
        config(['app.psychologist_invitation_url' => self::LINK]);

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $this->adminToken = $this->adminToken(['assessment_providers.manage']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions, string $email = 'ops@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    /** @param  array<string, mixed>|null  $body */
    private function admin(string $method, string $uri, ?array $body = null, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->json($method, '/api/v1/admin/assessment/'.$uri, $body ?? []);
    }

    private function provider(string $name = 'Lembaga Psikologi Contoh'): int
    {
        return (int) $this->admin('POST', 'providers', ['code' => strtoupper(substr((string) preg_replace('/[^A-Za-z]/', '', $name), 0, 4)), 'name' => $name, 'legal_name' => 'PT '.$name])->assertCreated()->json('data.id');
    }

    /** @param  array<string, mixed>  $overrides */
    private function invite(int $providerId, array $overrides = []): TestResponse
    {
        return $this->admin('POST', "providers/{$providerId}/invitations", [
            'email' => 'Dewi@Example.test',
            'name' => 'Dewi Lestari',
            'str_number' => 'STR-123',
            'str_valid_until' => '2030-01-31',
            'silp_number' => 'SIPP-456',
            'silp_valid_until' => '2029-06-30',
            ...$overrides,
        ]);
    }

    /** The plain token that the latest invitation email carried, read from the message the mail transport received. */
    private function tokenFromMail(): string
    {
        $messages = $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all();
        $this->assertNotEmpty($messages, 'No email was sent.');

        preg_match('#careers\.test/provider/invitations/([A-Za-z0-9_-]{43})#', (string) end($messages)->getOriginalMessage()->getTextBody(), $matches);
        $this->assertNotEmpty($matches, 'The invitation email holds no invitation link.');

        return $matches[1];
    }

    private function accept(string $token, string $password = 'password-123'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson("/api/v1/provider/invitations/{$token}/accept", ['password' => $password]);
    }

    /** Invites and accepts, and returns the psychologist id. */
    private function psychologist(int $providerId, string $email = 'dewi@example.test'): int
    {
        $this->invite($providerId, ['email' => $email])->assertCreated();
        $this->accept($this->tokenFromMail())->assertCreated();

        return (int) DB::table('assessment_psychologists')->orderByDesc('id')->value('id');
    }

    public function test_an_admin_registers_a_provider_and_the_action_is_audited(): void
    {
        $this->admin('POST', 'providers', ['code' => 'lpc', 'name' => 'Lembaga Psikologi Contoh', 'legal_name' => 'PT Lembaga Psikologi Contoh'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'LPC')
            ->assertJsonPath('data.name', 'Lembaga Psikologi Contoh')
            ->assertJsonPath('data.legal_name', 'PT Lembaga Psikologi Contoh')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('assessment_providers', ['code' => 'LPC', 'name' => 'Lembaga Psikologi Contoh', 'status' => 'active']);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.provider_registered', 'actor_type' => 'admin', 'subject_type' => 'assessment_provider']);
    }

    public function test_a_provider_name_is_unique_ignoring_case(): void
    {
        $this->provider('Lembaga Psikologi Contoh');

        $this->admin('POST', 'providers', ['code' => 'LAIN', 'name' => 'lembaga psikologi contoh', 'legal_name' => 'PT Lain'])
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:onesis:error:provider-name-taken');
    }

    public function test_a_provider_code_is_unique_and_made_of_two_to_ten_letters_or_digits(): void
    {
        $this->provider('Lembaga Psikologi Contoh');

        $this->admin('POST', 'providers', ['code' => 'lemb', 'name' => 'Biro Lain', 'legal_name' => 'PT Biro Lain'])
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:onesis:error:provider-code-taken');

        foreach (['A', 'TOOLONGCODE1', 'AB-C', 'AB C'] as $code) {
            $this->admin('POST', 'providers', ['code' => $code, 'name' => 'Biro Lain', 'legal_name' => 'PT Biro Lain'])->assertUnprocessable()->assertJsonValidationErrors(['code']);
        }
    }

    public function test_a_provider_needs_a_code_a_name_and_a_legal_name(): void
    {
        $this->admin('POST', 'providers', ['name' => 'A'])->assertUnprocessable()->assertJsonValidationErrors(['code', 'name', 'legal_name']);
    }

    public function test_an_admin_without_the_permission_is_refused(): void
    {
        $token = $this->adminToken([], 'weak@example.test');

        $this->admin('GET', 'providers', null, $token)->assertForbidden();
        $this->admin('POST', 'providers', ['code' => 'LEM', 'name' => 'Lembaga', 'legal_name' => 'PT Lembaga'], $token)->assertForbidden();
    }

    public function test_the_provider_endpoints_need_a_signed_in_admin(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/admin/assessment/providers')->assertUnauthorized();
    }

    public function test_providers_are_listed_filtered_shown_and_renamed(): void
    {
        $first = $this->provider('Alpha Psikologi');
        $second = $this->provider('Beta Konsultan');
        $this->admin('POST', "providers/{$second}/suspend")->assertNoContent();

        $this->admin('GET', 'providers')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'Alpha Psikologi')->assertJsonPath('meta.total', 2);
        $this->admin('GET', 'providers?filter[status]=suspended')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second);
        $this->admin('GET', 'providers?filter[q]=alpha')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first);
        $this->admin('GET', 'providers?filter[other]=1')->assertUnprocessable();

        $this->admin('GET', "providers/{$first}")->assertOk()->assertJsonPath('data.name', 'Alpha Psikologi');
        $this->admin('PUT', "providers/{$first}", ['code' => 'ALB', 'name' => 'Alpha Baru', 'legal_name' => 'PT Alpha Baru'])->assertOk()->assertJsonPath('data.name', 'Alpha Baru')->assertJsonPath('data.code', 'ALB');
        $this->admin('PUT', "providers/{$first}", ['code' => 'ALB', 'name' => 'beta konsultan', 'legal_name' => 'PT X'])->assertStatus(409);
        $this->admin('PUT', "providers/{$first}", ['code' => 'BETA', 'name' => 'Alpha Baru', 'legal_name' => 'PT X'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:provider-code-taken');
        $this->admin('GET', 'providers/999999')->assertNotFound();

        $this->admin('POST', "providers/{$second}/restore")->assertNoContent();
        $this->assertDatabaseHas('assessment_providers', ['id' => $second, 'status' => 'active']);
    }

    public function test_inviting_a_psychologist_sends_the_email_and_stores_only_the_hash(): void
    {
        $providerId = $this->provider();

        $this->invite($providerId)
            ->assertCreated()
            ->assertJsonPath('data.email', 'dewi@example.test')
            ->assertJsonPath('data.name', 'Dewi Lestari')
            ->assertJsonPath('data.str_valid_until', '2030-01-31')
            ->assertJsonPath('data.sent_at', '2026-10-11T09:00:00+00:00')
            ->assertJsonPath('data.expires_at', '2026-10-18T09:00:00+00:00')
            ->assertJsonMissingPath('data.token');

        $invitation = DB::table('assessment_psychologist_invitations')->first();
        $this->assertSame(hash('sha256', $this->tokenFromMail()), $invitation->token_hash);
        $this->assertNull($invitation->accepted_at);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.psychologist_invited', 'subject_id' => $providerId]);
    }

    public function test_the_invitation_email_names_the_provider_in_the_request_language(): void
    {
        $providerId = $this->provider();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->adminToken)->withHeader('Accept-Language', 'id')->postJson("/api/v1/admin/assessment/providers/{$providerId}/invitations", [
            'email' => 'dewi@example.test', 'name' => 'Dewi', 'str_number' => 'STR-1', 'str_valid_until' => '2030-01-31', 'silp_number' => 'SIPP-1', 'silp_valid_until' => '2030-01-31',
        ])->assertCreated();

        $message = $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()[0]->getOriginalMessage();

        $this->assertSame('dewi@example.test', $message->getTo()[0]->getAddress());
        $this->assertSame('Undangan psikolog mitra Lembaga Psikologi Contoh', $message->getSubject());
        $this->assertStringContainsString('berlaku selama 7 hari', (string) $message->getTextBody());
    }

    public function test_an_email_that_already_has_an_account_cannot_be_invited(): void
    {
        $providerId = $this->provider();
        DB::table('identity_users')->insert(['type' => 'job_seeker', 'name' => 'Taken', 'email' => 'dewi@example.test', 'status' => 'active', 'locale' => 'id', 'created_at' => now(), 'updated_at' => now()]);

        $this->invite($providerId)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:email-already-registered');
        $this->assertSame(0, DB::table('assessment_psychologist_invitations')->count());
    }

    public function test_a_suspended_provider_cannot_invite(): void
    {
        $providerId = $this->provider();
        $this->admin('POST', "providers/{$providerId}/suspend")->assertNoContent();

        $this->invite($providerId)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:provider-suspended');
    }

    public function test_an_invitation_for_a_provider_that_does_not_exist_is_a_404(): void
    {
        $this->invite(999999)->assertNotFound();
    }

    public function test_the_invitation_link_must_be_configured(): void
    {
        $providerId = $this->provider();
        config(['app.psychologist_invitation_url' => null]);

        $this->invite($providerId)->assertStatus(503)->assertJsonPath('type', 'urn:onesis:error:invitation-link-unavailable');
        $this->assertSame(0, DB::table('assessment_psychologist_invitations')->count());
    }

    public function test_a_second_invitation_to_the_same_email_waits_a_minute_and_then_revokes_the_first(): void
    {
        $providerId = $this->provider();
        $this->invite($providerId)->assertCreated();

        $this->invite($providerId)->assertStatus(429)->assertJsonPath('type', 'urn:onesis:error:invitation-cooldown');

        Carbon::setTestNow('2026-10-11 09:01:00');
        $this->invite($providerId)->assertCreated();

        $this->assertSame(1, DB::table('assessment_psychologist_invitations')->whereNull('revoked_at')->count());
        $this->assertSame(1, DB::table('assessment_psychologist_invitations')->whereNotNull('revoked_at')->count());
    }

    public function test_pending_invitations_are_listed_and_revoked(): void
    {
        $providerId = $this->provider();
        $id = (int) $this->invite($providerId)->json('data.id');

        $this->admin('GET', "providers/{$providerId}/invitations")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);

        $this->admin('DELETE', "providers/{$providerId}/invitations/{$id}")->assertNoContent();
        $this->admin('GET', "providers/{$providerId}/invitations")->assertOk()->assertJsonCount(0, 'data');
        $this->accept($this->tokenFromMail())->assertNotFound();

        $this->admin('DELETE', "providers/{$providerId}/invitations/999999")->assertNotFound();
    }

    public function test_the_invitee_previews_the_invitation(): void
    {
        $this->invite($this->provider())->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/provider/invitations/'.$this->tokenFromMail())
            ->assertOk()
            ->assertJsonPath('data.provider_name', 'Lembaga Psikologi Contoh')
            ->assertJsonPath('data.name', 'Dewi Lestari')
            ->assertJsonPath('data.email', 'dewi@example.test')
            ->assertJsonPath('data.expires_at', '2026-10-18T09:00:00+00:00');

        $this->getJson('/api/v1/provider/invitations/'.str_repeat('a', 43))->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:invitation-not-found');
    }

    public function test_accepting_creates_a_psychologist_account_without_a_token(): void
    {
        $providerId = $this->provider();
        $this->invite($providerId)->assertCreated();

        $this->accept($this->tokenFromMail())
            ->assertCreated()
            ->assertJsonPath('data.email', 'dewi@example.test')
            ->assertJsonPath('data.provider_name', 'Lembaga Psikologi Contoh')
            ->assertJsonPath('data.next', 'sign_in')
            ->assertJsonMissingPath('data.token');

        $user = DB::table('identity_users')->where('email', 'dewi@example.test')->first();
        $this->assertSame('psychologist', $user->type);
        $this->assertSame('Dewi Lestari', $user->name);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);

        $psychologist = DB::table('assessment_psychologists')->first();
        $this->assertSame((int) $user->id, (int) $psychologist->user_id);
        $this->assertSame($providerId, (int) $psychologist->provider_id);
        $this->assertSame('STR-123', $psychologist->str_number);
        $this->assertSame('2029-06-30', $psychologist->silp_valid_until);
        $this->assertNotNull(DB::table('assessment_psychologist_invitations')->value('accepted_at'));
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.psychologist_joined', 'actor_type' => 'user', 'actor_id' => $user->id]);
    }

    public function test_an_invitation_works_once(): void
    {
        $this->invite($this->provider())->assertCreated();
        $token = $this->tokenFromMail();

        $this->accept($token)->assertCreated();
        $this->accept($token)->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:invitation-not-found');
    }

    public function test_an_invitation_expires_after_seven_days(): void
    {
        $this->invite($this->provider())->assertCreated();
        $token = $this->tokenFromMail();

        Carbon::setTestNow('2026-10-18 09:00:01');

        $this->accept($token)->assertNotFound();
    }

    public function test_the_password_must_be_a_new_password(): void
    {
        $this->invite($this->provider())->assertCreated();

        $this->accept($this->tokenFromMail(), 'short')->assertUnprocessable()->assertJsonValidationErrors(['password']);
        $this->assertSame(0, DB::table('assessment_psychologists')->count());
    }

    public function test_a_suspended_provider_blocks_the_acceptance(): void
    {
        $providerId = $this->provider();
        $this->invite($providerId)->assertCreated();
        $this->admin('POST', "providers/{$providerId}/suspend")->assertNoContent();

        $this->accept($this->tokenFromMail())->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:provider-suspended');
    }

    public function test_an_email_registered_after_the_invitation_blocks_the_acceptance(): void
    {
        $this->invite($this->provider())->assertCreated();
        DB::table('identity_users')->insert(['type' => 'job_seeker', 'name' => 'Late', 'email' => 'dewi@example.test', 'status' => 'active', 'locale' => 'id', 'created_at' => now(), 'updated_at' => now()]);

        $this->accept($this->tokenFromMail())->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:email-already-registered');
        $this->assertSame(0, DB::table('assessment_psychologists')->count());
    }

    public function test_psychologists_are_listed_with_their_account_and_whether_they_may_sign(): void
    {
        $providerId = $this->provider();
        $id = $this->psychologist($providerId);

        $this->admin('GET', "providers/{$providerId}/psychologists")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.name', 'Dewi Lestari')
            ->assertJsonPath('data.0.email', 'dewi@example.test')
            ->assertJsonPath('data.0.account_status', 'active')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.can_sign_reports', true)
            ->assertJsonPath('meta.total', 1);

        $this->admin('GET', 'providers/999999/psychologists')->assertNotFound();
    }

    public function test_an_expired_licence_stops_the_psychologist_from_signing_and_a_renewal_restores_it(): void
    {
        $providerId = $this->provider();
        $id = $this->psychologist($providerId);

        $this->admin('PUT', "psychologists/{$id}", [
            'str_number' => 'STR-OLD', 'str_valid_until' => '2026-10-10', 'silp_number' => 'SIPP-456', 'silp_valid_until' => '2029-06-30',
        ])->assertOk()->assertJsonPath('data.can_sign_reports', false)->assertJsonPath('data.str_number', 'STR-OLD');

        $this->admin('PUT', "psychologists/{$id}", [
            'str_number' => 'STR-NEW', 'str_valid_until' => '2026-10-11', 'silp_number' => 'SIPP-456', 'silp_valid_until' => '2026-10-11',
        ])->assertOk()->assertJsonPath('data.can_sign_reports', true);

        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.psychologist_credentials_changed', 'subject_id' => $id]);
        $this->admin('PUT', "psychologists/{$id}", ['str_number' => 'X'])->assertUnprocessable();
        $this->admin('PUT', 'psychologists/999999', [
            'str_number' => 'STR-NEW', 'str_valid_until' => '2030-01-01', 'silp_number' => 'SIPP-456', 'silp_valid_until' => '2030-01-01',
        ])->assertNotFound();
    }

    public function test_suspending_a_psychologist_closes_the_account_and_revokes_its_tokens(): void
    {
        $id = $this->psychologist($this->provider());
        $userId = (int) DB::table('assessment_psychologists')->where('id', $id)->value('user_id');
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'user', 'tokenable_id' => $userId, 'name' => 'api', 'token' => hash('sha256', 'probe'), 'abilities' => '["*"]', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->admin('POST', "psychologists/{$id}/suspend")->assertNoContent();

        $this->assertDatabaseHas('assessment_psychologists', ['id' => $id, 'status' => 'suspended']);
        $this->assertDatabaseHas('identity_users', ['id' => $userId, 'status' => 'deactivated']);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $userId)->count());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.psychologist_suspended', 'subject_id' => $id]);

        $this->admin('POST', "psychologists/{$id}/restore")->assertNoContent();

        $this->assertDatabaseHas('assessment_psychologists', ['id' => $id, 'status' => 'active']);
        $this->assertDatabaseHas('identity_users', ['id' => $userId, 'status' => 'active']);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.psychologist_restored', 'subject_id' => $id]);
    }

    public function test_an_admin_resets_the_second_factor_of_a_psychologist(): void
    {
        $id = $this->psychologist($this->provider());
        $userId = (int) DB::table('assessment_psychologists')->where('id', $id)->value('user_id');
        DB::table('identity_user_two_factor')->insert(['user_id' => $userId, 'secret' => 'x', 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('identity_user_recovery_codes')->insert(['user_id' => $userId, 'code_hash' => 'h', 'created_at' => now()]);

        $this->admin('POST', "psychologists/{$id}/reset-two-factor")->assertNoContent();

        $this->assertSame(0, DB::table('identity_user_two_factor')->where('user_id', $userId)->count());
        $this->assertSame(0, DB::table('identity_user_recovery_codes')->where('user_id', $userId)->count());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.psychologist_two_factor_reset', 'subject_id' => $id]);
    }

    public function test_the_audit_entries_never_hold_an_email_address(): void
    {
        $this->psychologist($this->provider());

        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'like', 'assessment.%')->whereRaw('metadata::text like ?', ['%@%'])->count());
    }
}
