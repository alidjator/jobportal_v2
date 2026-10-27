<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * The leads of an institution manage its team (`docs/design/phase-8-provider-institutions.md` section 1): invite, revoke, suspend, restore and set the
 * role, never leaving the institution without a lead.
 */
final class ProviderTeamTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private const string LINK = 'https://careers.test/provider/invitations/{token}';

    private int $leadId;

    private int $colleagueId;

    private string $colleagueToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-27 09:00:00');
        config(['app.psychologist_invitation_url' => self::LINK]);

        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();

        // The psychologist of the first institution leads it; a colleague joins as a member.
        $this->leadId = (int) DB::table('assessment_psychologists')->where('provider_id', $this->providerId)->value('id');
        DB::table('assessment_psychologists')->where('id', $this->leadId)->update(['role' => 'lead']);
        $this->colleagueToken = $this->colleagueOf($this->providerId, 'rekan@example.test');
        $this->colleagueId = (int) DB::table('assessment_psychologists')->where('provider_id', $this->providerId)->where('id', '!=', $this->leadId)->value('id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    /** @param  array<string, mixed>  $body */
    private function team(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        return $this->provider($method, 'team'.($uri === '' ? '' : '/'.$uri), $body, $token);
    }

    /** @param  array<string, mixed>  $overrides */
    private function invite(array $overrides = [], ?string $token = null): TestResponse
    {
        return $this->team('POST', 'invitations', ['email' => 'Baru@Example.test', 'name' => 'Rani Baru', ...$overrides], $token);
    }

    private function tokenFromMail(): string
    {
        $messages = $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all();
        $this->assertNotEmpty($messages, 'No email was sent.');

        preg_match('#careers\.test/provider/invitations/([A-Za-z0-9_-]{43})#', (string) end($messages)->getOriginalMessage()->getTextBody(), $matches);
        $this->assertNotEmpty($matches, 'The invitation email holds no invitation link.');

        return $matches[1];
    }

    // ----- reading the team -----

    public function test_a_lead_sees_the_team_with_roles_and_licences(): void
    {
        $this->team('GET', '')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->leadId)
            ->assertJsonPath('data.0.role', 'lead')
            ->assertJsonPath('data.0.str_number', 'STR-1')
            ->assertJsonPath('data.0.can_sign_reports', true)
            ->assertJsonPath('data.1.id', $this->colleagueId)
            ->assertJsonPath('data.1.role', 'member');
    }

    public function test_a_member_cannot_manage_the_team(): void
    {
        $this->team('GET', '', [], $this->colleagueToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-lead');
        $this->invite([], $this->colleagueToken)->assertForbidden();
        $this->team('POST', "{$this->leadId}/suspend", [], $this->colleagueToken)->assertForbidden();
        $this->assertSame(0, DB::table('assessment_psychologist_invitations')->count());
    }

    public function test_the_team_endpoints_need_a_signed_in_psychologist(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withoutToken()->getJson('/api/v1/provider/team')->assertUnauthorized();
    }

    // ----- invitations -----

    public function test_a_lead_invites_a_colleague_without_a_licence(): void
    {
        $this->invite()
            ->assertCreated()
            ->assertJsonPath('data.email', 'baru@example.test')
            ->assertJsonPath('data.role', 'member')
            ->assertJsonPath('data.invited_by', 'lead')
            ->assertJsonPath('data.str_number', null)
            ->assertJsonPath('data.silp_valid_until', null);

        $row = DB::table('assessment_psychologist_invitations')->first();
        $this->assertSame($this->providerId, (int) $row->provider_id);
        $this->assertNull($row->invited_by_admin_id);
        $this->assertSame($this->leadId, (int) $row->invited_by_psychologist_id);
        $this->assertSame(64, strlen((string) $row->token_hash));

        $this->team('GET', 'invitations')->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.psychologist_invited', 'actor_type' => 'user', 'subject_id' => $this->providerId]);
    }

    public function test_the_colleague_joins_with_the_invited_role_and_cannot_sign_until_a_licence_is_approved(): void
    {
        $this->invite(['role' => 'lead'])->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/provider/invitations/'.$this->tokenFromMail().'/accept', ['password' => 'password-123'])->assertCreated();

        $joined = DB::table('assessment_psychologists')->where('provider_id', $this->providerId)->orderByDesc('id')->first();
        $this->assertSame('lead', $joined->role);
        $this->assertNull($joined->str_number);
        $this->assertNull($joined->silp_valid_until);

        $this->assessmentAdmin('GET', "providers/{$this->providerId}/psychologists")
            ->assertOk()
            ->assertJsonPath('data.2.id', (int) $joined->id)
            ->assertJsonPath('data.2.str_number', null)
            ->assertJsonPath('data.2.can_sign_reports', false);
    }

    public function test_a_second_invitation_to_the_same_email_waits_and_an_email_with_an_account_is_refused(): void
    {
        $this->invite()->assertCreated();
        $this->invite()->assertStatus(429)->assertJsonPath('type', 'urn:onesis:error:invitation-cooldown');
        $this->invite(['email' => 'rekan@example.test'])->assertConflict()->assertJsonPath('type', 'urn:onesis:error:email-already-registered');
        $this->assertSame(1, DB::table('assessment_psychologist_invitations')->count());
    }

    public function test_the_invitation_needs_an_email_a_name_and_a_known_role(): void
    {
        $this->invite(['email' => 'not-an-email'])->assertUnprocessable();
        $this->invite(['name' => ''])->assertUnprocessable();
        $this->invite(['role' => 'owner'])->assertUnprocessable();
    }

    public function test_a_lead_revokes_an_invitation_of_their_own_institution_only(): void
    {
        $id = (int) $this->invite()->assertCreated()->json('data.id');

        $other = (int) DB::table('assessment_psychologist_invitations')->insertGetId([
            'provider_id' => $this->otherProviderId, 'email' => 'x@example.test', 'name' => 'X', 'role' => 'member', 'token_hash' => str_repeat('b', 64),
            'invited_by_admin_id' => $this->assessmentAdminId, 'sent_at' => now(), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->team('DELETE', "invitations/{$other}")->assertNotFound();
        $this->assertNull(DB::table('assessment_psychologist_invitations')->where('id', $other)->value('revoked_at'));

        $this->team('DELETE', "invitations/{$id}")->assertNoContent();
        $this->team('GET', 'invitations')->assertOk()->assertJsonCount(0, 'data');
    }

    // ----- suspending and restoring -----

    public function test_a_lead_suspends_and_restores_a_colleague(): void
    {
        $userId = (int) DB::table('assessment_psychologists')->where('id', $this->colleagueId)->value('user_id');

        $this->team('POST', "{$this->colleagueId}/suspend")->assertNoContent();

        $this->assertDatabaseHas('assessment_psychologists', ['id' => $this->colleagueId, 'status' => 'suspended']);
        $this->assertDatabaseHas('identity_users', ['id' => $userId, 'status' => 'deactivated']);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $userId)->count());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.psychologist_suspended', 'actor_type' => 'user', 'subject_id' => $this->colleagueId]);

        $this->team('POST', "{$this->colleagueId}/restore")->assertNoContent();

        $this->assertDatabaseHas('assessment_psychologists', ['id' => $this->colleagueId, 'status' => 'active']);
        $this->assertDatabaseHas('identity_users', ['id' => $userId, 'status' => 'active']);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.psychologist_restored', 'actor_type' => 'user', 'subject_id' => $this->colleagueId]);
    }

    public function test_a_lead_cannot_suspend_themselves(): void
    {
        $this->team('POST', "{$this->leadId}/suspend")->assertConflict()->assertJsonPath('type', 'urn:onesis:error:cannot-suspend-self');
        $this->assertDatabaseHas('assessment_psychologists', ['id' => $this->leadId, 'status' => 'active']);
    }

    public function test_a_lead_cannot_reach_a_psychologist_of_another_institution(): void
    {
        $otherId = (int) DB::table('assessment_psychologists')->where('provider_id', $this->otherProviderId)->value('id');

        $this->team('POST', "{$otherId}/suspend")->assertNotFound();
        $this->team('POST', "{$otherId}/restore")->assertNotFound();
        $this->team('PUT', "{$otherId}/role", ['role' => 'lead'])->assertNotFound();
        $this->assertDatabaseHas('assessment_psychologists', ['id' => $otherId, 'status' => 'active', 'role' => 'member']);
    }

    // ----- roles and the last lead -----

    public function test_a_lead_changes_the_role_of_a_colleague_and_the_change_is_audited(): void
    {
        $this->team('PUT', "{$this->colleagueId}/role", ['role' => 'lead'])->assertOk()->assertJsonPath('data.role', 'lead');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.psychologist_role_changed', 'actor_type' => 'user', 'subject_id' => $this->colleagueId]);

        // Another lead is there now, so the first lead may step down.
        $this->team('PUT', "{$this->leadId}/role", ['role' => 'member'])->assertOk()->assertJsonPath('data.role', 'member');

        $this->team('PUT', "{$this->colleagueId}/role", ['role' => 'owner'])->assertUnprocessable();
    }

    public function test_the_institution_keeps_at_least_one_active_lead(): void
    {
        $this->team('PUT', "{$this->leadId}/role", ['role' => 'member'])->assertConflict()->assertJsonPath('type', 'urn:onesis:error:last-lead');
        $this->assertDatabaseHas('assessment_psychologists', ['id' => $this->leadId, 'role' => 'lead']);

        // A suspended lead does not count: with the second lead suspended, the first is the last one again.
        $this->team('PUT', "{$this->colleagueId}/role", ['role' => 'lead'])->assertOk();
        $this->team('POST', "{$this->colleagueId}/suspend")->assertNoContent();
        $this->team('PUT', "{$this->leadId}/role", ['role' => 'member'])->assertConflict();

        // The platform admin is bound by the same rule.
        $this->assessmentAdmin('POST', "psychologists/{$this->leadId}/suspend")->assertConflict()->assertJsonPath('type', 'urn:onesis:error:last-lead');
    }

    public function test_the_platform_admin_sets_the_role_and_invites_a_lead(): void
    {
        $this->assessmentAdmin('PUT', "psychologists/{$this->colleagueId}", [
            'str_number' => 'STR-2', 'str_valid_until' => '2030-01-31', 'silp_number' => 'SIPP-2', 'silp_valid_until' => '2030-01-31', 'role' => 'lead',
        ])->assertOk()->assertJsonPath('data.role', 'lead');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.psychologist_role_changed', 'actor_type' => 'admin', 'subject_id' => $this->colleagueId]);

        // Without `role` the role stays.
        $this->assessmentAdmin('PUT', "psychologists/{$this->colleagueId}", [
            'str_number' => 'STR-3', 'str_valid_until' => '2030-01-31', 'silp_number' => 'SIPP-2', 'silp_valid_until' => '2030-01-31',
        ])->assertOk()->assertJsonPath('data.role', 'lead');

        $this->assessmentAdmin('POST', "providers/{$this->otherProviderId}/invitations", [
            'email' => 'ketua@example.test', 'name' => 'Ketua Baru', 'role' => 'lead',
            'str_number' => 'STR-9', 'str_valid_until' => '2030-01-31', 'silp_number' => 'SIPP-9', 'silp_valid_until' => '2030-01-31',
        ])->assertCreated()->assertJsonPath('data.role', 'lead')->assertJsonPath('data.invited_by', 'admin');
    }
}
