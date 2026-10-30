<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** Pre-employment checks on an application (`docs/design/phase-8-pre-employment-checks.md`): types, the checklist, results and the candidate's consent. */
final class PreEmploymentChecksTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $applicationId;

    private string $candidateToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->app->make(SettingsStore::class)->setGlobal('recruitment.pre_employment_checks_enabled', true, null);

        $userId = $this->seeker('dewi@example.test', name: 'Dewi Lestari');
        $this->candidateToken = $this->tokenFor($userId);
        $resume = $this->completeProfile($this->candidateToken);
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId, $this->secondRecruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job, ['resume_id' => $resume], $this->candidateToken)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function toStage(string $type): void
    {
        $body = ['stage_id' => $this->stage($type)];

        // The interview stage asks for a schedule with the move.
        if ($type === 'interview') {
            $body['interview'] = $this->interviewSchedule();
        }

        $this->api('POST', "/company/applications/{$this->applicationId}/move", $body, $this->recruiterToken)->assertSuccessful();
    }

    /** A member who sees the job but holds no `checks.manage`. */
    private function withoutChecksPermission(): void
    {
        $memberId = (int) DB::table('company_members')->where('user_id', $this->secondRecruiterId)->value('id');
        DB::table('company_member_permissions')->where('company_member_id', $memberId)->where('permission', 'checks.manage')->delete();
    }

    /** @param  array<string, mixed>  $body */
    private function type(array $body = [], ?string $token = null): TestResponse
    {
        return $this->api('POST', '/company/check-types', ['name' => 'MCU', 'kind' => 'medical', ...$body], $token ?? $this->recruiterToken);
    }

    /** @param  array<string, mixed>  $body */
    private function request(int $typeId, array $body = [], ?string $token = null): TestResponse
    {
        return $this->api('POST', "/company/applications/{$this->applicationId}/checks", ['check_type_id' => $typeId, ...$body], $token ?? $this->recruiterToken);
    }

    private function consent(int $checkId, ?string $token = null): TestResponse
    {
        return $this->api('POST', "/me/applications/{$this->applicationId}/checks/{$checkId}/consent", ['agree' => true], $token ?? $this->candidateToken);
    }

    // ----- the switch and permissions -----

    public function test_the_feature_is_off_until_it_is_switched_on(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('recruitment.pre_employment_checks_enabled', false, null);

        $this->type()->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:feature-disabled');
        $this->api('GET', "/company/applications/{$this->applicationId}/checks", token: $this->recruiterToken)->assertForbidden();
        $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->candidateToken)->assertOk()->assertJsonPath('data.checks', []);
    }

    public function test_only_a_member_with_the_permission_defines_types_and_requests_checks(): void
    {
        $this->withoutChecksPermission();
        $this->toStage('interview');

        $this->type(token: $this->secondRecruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        $this->api('GET', '/company/check-types', token: $this->secondRecruiterToken)->assertForbidden();
        $this->type(token: $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');

        $typeId = (int) $this->type()->assertCreated()->json('data.id');
        $this->request($typeId, token: $this->secondRecruiterToken)->assertForbidden();
        $this->request($typeId, token: $this->otherAdminToken)->assertNotFound();
        $this->assertSame(0, DB::table('recruitment_application_checks')->count());
    }

    // ----- types -----

    public function test_a_type_is_defined_changed_and_deactivated_and_medical_always_needs_consent(): void
    {
        $response = $this->type(['requires_consent' => false, 'instructions' => '  Bring your ID.  '])->assertCreated();
        $response->assertJsonPath('data.requires_consent', true)->assertJsonPath('data.instructions', 'Bring your ID.')->assertJsonPath('data.is_active', true);
        $id = (int) $response->json('data.id');

        $other = (int) $this->type(['name' => 'Verifikasi ijazah', 'kind' => 'education', 'requires_consent' => false])->assertCreated()->assertJsonPath('data.requires_consent', false)->json('data.id');
        $this->api('PATCH', "/company/check-types/{$other}", ['name' => 'Cek ijazah', 'instructions' => null, 'requires_consent' => true], $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.name', 'Cek ijazah')->assertJsonPath('data.kind', 'education')->assertJsonPath('data.requires_consent', true);

        $this->type(['name' => 'mcu'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:check-type-name-taken');
        $this->type(['name' => ''])->assertStatus(422);
        $this->type(['kind' => 'dentist'])->assertStatus(422);
        $this->type(['name' => 'Link', 'kind' => 'other', 'instructions' => 'See https://evil.example/form'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');

        $this->api('POST', "/company/check-types/{$id}/deactivate", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.is_active', false);
        $this->api('GET', '/company/check-types', token: $this->recruiterToken)->assertOk()->assertJsonCount(2, 'data');
        $this->api('POST', "/company/check-types/{$id}/reactivate", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.is_active', true);
        // The admin of another company holds every permission there and sees only their own (empty) list.
        $this->api('GET', '/company/check-types', token: $this->otherAdminToken)->assertOk()->assertJsonCount(0, 'data');
    }

    // ----- requesting -----

    public function test_a_check_is_requested_from_the_interview_stage_and_once_per_type(): void
    {
        $typeId = (int) $this->type()->assertCreated()->json('data.id');

        $this->request($typeId)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:check-not-allowed-stage');

        $this->toStage('interview');
        $response = $this->request($typeId, ['scheduled_at' => '2026-11-20T09:00:00+07:00', 'place' => 'Klinik Sehat', 'vendor' => 'PT Klinik', 'note_to_candidate' => 'Fast for 8 hours.'])->assertCreated();
        $response->assertJsonPath('data.status', 'requested')->assertJsonPath('data.consent_pending', true)->assertJsonPath('data.type.kind', 'medical')->assertJsonPath('data.place', 'Klinik Sehat');

        $this->request($typeId)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:check-already-open');

        $inactive = (int) $this->type(['name' => 'Lama', 'kind' => 'other'])->json('data.id');
        $this->api('POST', "/company/check-types/{$inactive}/deactivate", token: $this->recruiterToken)->assertOk();
        $this->request($inactive)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:check-type-inactive');
        $this->request(999_999)->assertNotFound();
        $this->request($typeId, ['note_to_candidate' => 'Mail me at x@evil.example'])->assertStatus(422);
    }

    // ----- consent and the candidate's side -----

    public function test_consent_moves_a_check_to_scheduled_and_a_refusal_is_shown_to_the_team(): void
    {
        $this->toStage('interview');
        $typeId = (int) $this->type()->json('data.id');
        $checkId = (int) $this->request($typeId, ['scheduled_at' => '2026-11-20T09:00:00+07:00'])->assertCreated()->json('data.id');

        $this->api('POST', "/me/applications/{$this->applicationId}/checks/{$checkId}/refuse", ['reason' => 'No time'], $this->candidateToken)->assertNoContent();
        $this->api('GET', "/company/applications/{$this->applicationId}/checks", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.0.status', 'requested')->assertJsonPath('data.0.refusal_reason', 'No time')->assertJsonPath('data.0.consent_pending', true);

        $this->consent($checkId)->assertNoContent();
        $this->api('GET', "/company/applications/{$this->applicationId}/checks", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.0.status', 'scheduled')->assertJsonPath('data.0.consent_pending', false)->assertJsonPath('data.0.refusal_reason', null);

        $mine = $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->candidateToken)->assertOk()->json('data.checks.0');
        $this->assertSame('MCU', $mine['name']);
        $this->assertSame('scheduled', $mine['status']);
        $this->assertArrayNotHasKey('outcome', $mine);
        $this->assertArrayNotHasKey('result_note', $mine);

        $this->consent($checkId, $this->tokenFor($this->seeker('other@example.test')))->assertNotFound();
        $this->consent($checkId, $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
    }

    public function test_a_check_without_consent_needs_no_answer(): void
    {
        $this->toStage('interview');
        $typeId = (int) $this->type(['name' => 'Ijazah', 'kind' => 'education', 'requires_consent' => false])->json('data.id');
        $checkId = (int) $this->request($typeId, ['scheduled_at' => '2026-11-20T09:00:00+07:00'])->assertCreated()->assertJsonPath('data.status', 'scheduled')->json('data.id');

        $this->consent($checkId)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:check-consent-not-required');
    }

    // ----- change, waive, cancel -----

    public function test_the_team_changes_waives_and_cancels_an_open_check(): void
    {
        $this->toStage('interview');
        $typeId = (int) $this->type()->json('data.id');
        $checkId = (int) $this->request($typeId)->json('data.id');

        $this->api('PATCH', "/company/applications/{$this->applicationId}/checks/{$checkId}", ['scheduled_at' => '2026-11-21T10:00:00+07:00', 'place' => 'Klinik B'], $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.place', 'Klinik B')->assertJsonPath('data.vendor', null);

        $this->api('POST', "/company/applications/{$this->applicationId}/checks/{$checkId}/cancel", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->api('POST', "/company/applications/{$this->applicationId}/checks/{$checkId}/waive", token: $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:check-closed');
        $this->api('PATCH', "/company/applications/{$this->applicationId}/checks/{$checkId}", ['place' => 'X'], $this->recruiterToken)->assertStatus(409);

        // A closed check frees its type for a new request.
        $second = (int) $this->request($typeId)->assertCreated()->json('data.id');
        $this->api('POST', "/company/applications/{$this->applicationId}/checks/{$second}/waive", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.status', 'waived');
    }

    // ----- results -----

    public function test_a_result_waits_for_consent_and_completes_the_check_with_an_audit_entry(): void
    {
        $this->toStage('interview');
        $typeId = (int) $this->type()->json('data.id');
        $checkId = (int) $this->request($typeId)->json('data.id');
        $uri = "/company/applications/{$this->applicationId}/checks/{$checkId}/result";

        $this->api('POST', $uri, ['outcome' => 'pass'], $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:check-consent-pending');
        $this->consent($checkId)->assertNoContent();

        $this->api('POST', $uri, ['outcome' => 'excellent'], $this->recruiterToken)->assertStatus(422);
        $this->api('POST', $uri, ['outcome' => 'pass_with_notes', 'note' => 'Mild allergy.'], $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.status', 'completed')->assertJsonPath('data.outcome', 'pass_with_notes')->assertJsonPath('data.result_note', 'Mild allergy.')->assertJsonPath('data.outcome_visible', true);

        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'check.outcome_recorded')->count());
        $this->assertStringNotContainsString('Mild allergy', (string) DB::table('platform_audit_logs')->where('action', 'check.outcome_recorded')->value('metadata'));
        $this->api('POST', $uri, ['outcome' => 'pass'], $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:check-closed');

        $mine = $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->candidateToken)->assertOk()->json('data.checks.0');
        $this->assertSame('completed', $mine['status']);
        $this->assertArrayNotHasKey('outcome', $mine);
    }

    public function test_the_outcome_shows_only_to_checks_manage_and_a_medical_read_is_audited(): void
    {
        $this->toStage('interview');
        $typeId = (int) $this->type()->json('data.id');
        $checkId = (int) $this->request($typeId)->json('data.id');
        $this->consent($checkId)->assertNoContent();
        $this->api('POST', "/company/applications/{$this->applicationId}/checks/{$checkId}/result", ['outcome' => 'fail'], $this->recruiterToken)->assertOk();
        $this->withoutChecksPermission();

        $viewer = $this->api('GET', "/company/applications/{$this->applicationId}/checks", token: $this->secondRecruiterToken)->assertOk();
        $viewer->assertJsonPath('data.0.status', 'completed')->assertJsonPath('data.0.outcome_visible', false)->assertJsonPath('data.0.outcome', null)->assertJsonPath('data.0.result_note', null);
        // Recording the result showed it to the recorder (one audited read); the viewer without the permission adds none.
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'check.outcome_viewed')->count());

        $this->api('GET', "/company/applications/{$this->applicationId}/checks", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.0.outcome', 'fail');
        $this->assertSame(2, DB::table('platform_audit_logs')->where('action', 'check.outcome_viewed')->count());

        // An unfit result rejects nothing by itself.
        $this->assertSame($this->stage('interview'), (int) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_id'));
    }

    public function test_a_file_comes_with_non_medical_results_only_and_every_read_is_audited(): void
    {
        Storage::fake();
        $this->toStage('interview');
        $medical = (int) $this->type()->json('data.id');
        $police = (int) $this->type(['name' => 'SKCK', 'kind' => 'police_record'])->json('data.id');
        $medicalCheck = (int) $this->request($medical)->json('data.id');
        $policeCheck = (int) $this->request($police)->json('data.id');
        $this->consent($medicalCheck)->assertNoContent();
        $this->consent($policeCheck)->assertNoContent();

        $this->app['auth']->forgetGuards();
        $pdf = fn (string $content): UploadedFile => UploadedFile::fake()->createWithContent('skck.pdf', $content);
        $post = fn (int $id, array $body) => $this->withToken($this->recruiterToken)->post("/api/v1/company/applications/{$this->applicationId}/checks/{$id}/result", $body, ['Accept' => 'application/json']);

        $post($medicalCheck, ['outcome' => 'pass', 'file' => $pdf($this->pdf())])->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $post($policeCheck, ['outcome' => 'pass', 'file' => $pdf('plain text')])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-file');
        $this->app['auth']->forgetGuards();
        $post($policeCheck, ['outcome' => 'pass', 'file' => $pdf($this->pdf())])->assertOk()->assertJsonPath('data.has_result_file', true)->assertJsonPath('data.result_file_name', 'skck.pdf');

        $this->api('GET', "/company/applications/{$this->applicationId}/checks/{$policeCheck}/result/file", token: $this->recruiterToken)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'check.outcome_viewed')->whereRaw("metadata::text like '%\"file\":true%'")->count());
        $this->api('GET', "/company/applications/{$this->applicationId}/checks/{$medicalCheck}/result/file", token: $this->recruiterToken)->assertNotFound();
    }
}
