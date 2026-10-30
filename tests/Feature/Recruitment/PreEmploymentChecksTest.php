<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Platform\Application\Settings\SettingsStore;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
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

        // The offer stage asks for an offer with the move.
        if ($type === 'offer') {
            $body['offer'] = $this->offerDetails();
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

    /** @param  array<string, mixed>  $overrides */
    private function sendOffer(array $overrides = []): TestResponse
    {
        return $this->api('PUT', "/company/applications/{$this->applicationId}/offer", $this->offerDetails($overrides), $this->recruiterToken);
    }

    public function test_an_offer_can_depend_on_checks_and_names_them(): void
    {
        $this->toStage('offer');
        $medical = (int) $this->type()->json('data.id');
        $background = (int) $this->type(['name' => 'Background check', 'kind' => 'background'])->json('data.id');

        $this->sendOffer()->assertOk()->assertJsonPath('data.conditional_on_checks', false)->assertJsonPath('data.condition_check_types', []);

        $this->sendOffer(['conditional_on_checks' => true, 'condition_check_type_ids' => [$medical, $background, $medical]])->assertOk()
            ->assertJsonPath('data.conditional_on_checks', true)
            ->assertJsonPath('data.condition_check_types', [['id' => $medical, 'name' => 'MCU'], ['id' => $background, 'name' => 'Background check']]);
        $this->assertSame([$medical, $background], json_decode((string) DB::table('recruitment_offers')->where('application_id', $this->applicationId)->where('status', 'pending')->value('condition_check_type_ids'), true));

        $this->api('GET', "/me/applications/{$this->applicationId}/offer", token: $this->candidateToken)->assertOk()->assertJsonPath('data.condition_check_types.0.name', 'MCU');

        // Naming types while the offer is not conditional leaves the offer unconditional.
        $this->sendOffer(['conditional_on_checks' => false, 'condition_check_type_ids' => [$medical]])->assertOk()->assertJsonPath('data.conditional_on_checks', false);
    }

    public function test_a_conditional_offer_needs_types_of_the_company_that_are_in_use(): void
    {
        $this->toStage('offer');
        $type = (int) $this->type()->json('data.id');

        $this->sendOffer(['conditional_on_checks' => true])->assertStatus(422);
        $this->sendOffer(['conditional_on_checks' => true, 'condition_check_type_ids' => []])->assertStatus(422);
        $this->sendOffer(['conditional_on_checks' => true, 'condition_check_type_ids' => [999_999]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer');

        $this->api('POST', "/company/check-types/{$type}/deactivate", token: $this->recruiterToken)->assertSuccessful();
        $this->sendOffer(['conditional_on_checks' => true, 'condition_check_type_ids' => [$type]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer');
        $this->assertSame(0, DB::table('recruitment_offers')->where('application_id', $this->applicationId)->where('conditional_on_checks', true)->count());
    }

    public function test_a_conditional_offer_waits_for_the_switch(): void
    {
        $this->toStage('offer');
        $type = (int) $this->type()->json('data.id');
        $this->app->make(SettingsStore::class)->setGlobal('recruitment.pre_employment_checks_enabled', false, null);

        $this->sendOffer(['conditional_on_checks' => true, 'condition_check_type_ids' => [$type]])->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:feature-disabled');
        $this->sendOffer()->assertOk();
    }

    /** @return list<string> the types of the `check.*` notifications of the user, sorted (the notification ids do not follow the order they were written in) */
    private function checkNotifications(int $userId): array
    {
        $types = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', 'check.%')->pluck('type')->all();
        sort($types);

        return $types;
    }

    public function test_the_candidate_is_told_when_a_check_is_requested_scheduled_moved_or_cancelled(): void
    {
        $this->toStage('interview');
        $typeId = (int) $this->type(['name' => 'Verifikasi ijazah', 'kind' => 'education', 'requires_consent' => false])->json('data.id');
        $candidateId = (int) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('user_id');

        $checkId = (int) $this->request($typeId)->assertCreated()->json('data.id');
        $this->assertSame(['check.requested'], $this->checkNotifications($candidateId));
        $data = json_decode((string) DB::table('notifications')->where('notifiable_id', $candidateId)->where('type', 'check.requested')->value('data'), true);
        $this->assertSame(['application_id' => $this->applicationId, 'check_id' => $checkId, 'check_name' => 'Verifikasi ijazah', 'scheduled_at' => null], array_intersect_key($data, array_flip(['application_id', 'check_id', 'check_name', 'scheduled_at'])));

        $patch = fn (array $body) => $this->api('PATCH', "/company/applications/{$this->applicationId}/checks/{$checkId}", $body, $this->recruiterToken)->assertOk();
        $patch(['scheduled_at' => '2026-11-20T10:00:00+07:00']);
        $patch(['scheduled_at' => '2026-11-20T10:00:00+07:00', 'place' => 'Klinik B']);
        $patch(['scheduled_at' => '2026-11-21T10:00:00+07:00', 'place' => 'Klinik B']);
        $this->assertSame(['check.requested', 'check.rescheduled', 'check.scheduled'], $this->checkNotifications($candidateId));

        $this->api('POST', "/company/applications/{$this->applicationId}/checks/{$checkId}/cancel", token: $this->recruiterToken)->assertOk();
        $this->assertSame(['check.cancelled', 'check.requested', 'check.rescheduled', 'check.scheduled'], $this->checkNotifications($candidateId));

        // A waiver is not announced.
        $second = (int) $this->request($typeId)->assertCreated()->json('data.id');
        $this->api('POST', "/company/applications/{$this->applicationId}/checks/{$second}/waive", token: $this->recruiterToken)->assertOk();
        $this->assertSame(['check.cancelled', 'check.requested', 'check.requested', 'check.rescheduled', 'check.scheduled'], $this->checkNotifications($candidateId));
    }

    public function test_the_member_who_requested_a_check_is_told_when_the_candidate_refuses_it(): void
    {
        $this->toStage('interview');
        $checkId = (int) $this->request((int) $this->type()->json('data.id'))->assertCreated()->json('data.id');

        $this->api('POST', "/me/applications/{$this->applicationId}/checks/{$checkId}/refuse", ['reason' => 'No time'], $this->candidateToken)->assertNoContent();

        $this->assertSame(['check.consent_refused'], $this->checkNotifications($this->recruiterId));
        $this->assertSame([], $this->checkNotifications($this->secondRecruiterId));
    }

    /** @return int the id of a completed check with a result and a stored file */
    private function insertResult(string $kind, string $name): int
    {
        $type = DB::table('recruitment_check_types')->insertGetId(['company_id' => $this->companyId, 'name' => $name, 'kind' => $kind, 'requires_consent' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        Storage::put("recruitment/checks/{$name}.pdf", 'file');

        return (int) DB::table('recruitment_application_checks')->insertGetId([
            'application_id' => $this->applicationId, 'check_type_id' => $type, 'status' => 'completed', 'consent_required' => true, 'consented_at' => now(), 'outcome' => 'pass', 'result_note' => 'Fine.',
            'result_file_path' => "recruitment/checks/{$name}.pdf", 'result_file_name' => "{$name}.pdf", 'result_recorded_by' => $this->recruiterId, 'result_recorded_at' => now(), 'requested_by' => $this->recruiterId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function endApplication(string $stageType, int $daysAgo): void
    {
        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['stage_type' => $stageType, 'stage_entered_at' => now()->subDays($daysAgo)]);
    }

    public function test_the_results_go_ninety_days_after_the_application_ended(): void
    {
        Storage::fake();
        $medical = $this->insertResult('medical', 'mcu');
        $education = $this->insertResult('education', 'ijazah');

        // Still within 90 days, or not final: nothing goes.
        $this->endApplication('rejected', 89);
        $this->artisan('recruitment:purge-check-results')->assertSuccessful();
        $this->endApplication('interview', 200);
        $this->artisan('recruitment:purge-check-results')->assertSuccessful();
        $this->assertSame(2, DB::table('recruitment_application_checks')->whereNotNull('outcome')->whereNotNull('result_file_path')->count());

        $this->endApplication('rejected', 91);
        $this->artisan('recruitment:purge-check-results')->expectsOutput('Deleted the result of 2 checks.')->assertSuccessful();

        // A medical check loses outcome, note and file; any other check loses its file only, and both stay completed.
        $row = DB::table('recruitment_application_checks')->where('id', $medical)->first();
        $this->assertSame(['completed', null, null, null, null], [$row->status, $row->outcome, $row->result_note, $row->result_file_path, $row->result_file_name]);
        $row = DB::table('recruitment_application_checks')->where('id', $education)->first();
        $this->assertSame(['completed', 'pass', 'Fine.', null, null], [$row->status, $row->outcome, $row->result_note, $row->result_file_path, $row->result_file_name]);
        Storage::assertMissing(['recruitment/checks/mcu.pdf', 'recruitment/checks/ijazah.pdf']);

        // A second run finds nothing more to do.
        $this->artisan('recruitment:purge-check-results')->expectsOutput('Deleted the result of 0 checks.')->assertSuccessful();
    }

    public function test_a_hired_application_is_final_too(): void
    {
        Storage::fake();
        $medical = $this->insertResult('medical', 'mcu');
        $this->endApplication('hired', 120);

        $this->artisan('recruitment:purge-check-results')->assertSuccessful();

        $this->assertNull(DB::table('recruitment_application_checks')->where('id', $medical)->value('outcome'));
    }

    /** @return array{0: int, 1: int} the ids of two more applications to the job of the main one, by two other job seekers */
    private function moreApplications(): array
    {
        $job = (int) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('job_id');
        $ids = [];

        foreach (['budi.s@example.test' => 'Budi Santoso', 'ani.w@example.test' => 'Ani Wijaya'] as $email => $name) {
            $token = $this->tokenFor($this->seeker($email, name: $name));
            $ids[] = (int) $this->apply($job, ['resume_id' => $this->completeProfile($token)], $token)->assertCreated()->json('data.id');
        }

        return [$ids[0], $ids[1]];
    }

    private function moveToInterview(int $applicationId): void
    {
        $this->api('POST', "/company/applications/{$applicationId}/move", ['stage_id' => $this->stage('interview'), 'interview' => $this->interviewSchedule()], $this->recruiterToken)->assertSuccessful();
    }

    /** @param  array<string, mixed>  $body */
    private function bulkRequest(array $body, ?int $job = null, ?string $token = null): TestResponse
    {
        $job ??= (int) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('job_id');

        return $this->api('POST', "/company/jobs/{$job}/applications/bulk-checks", $body, $token ?? $this->recruiterToken);
    }

    public function test_a_check_is_requested_on_several_applications_and_each_answer_is_its_own(): void
    {
        [$budi, $ani] = $this->moreApplications();
        $typeId = (int) $this->type()->json('data.id');
        $this->toStage('interview');
        $this->moveToInterview($budi);
        $this->request($typeId)->assertCreated();

        // Dewi has the check already open, Budi is in an interview stage, Ani still applied.
        $response = $this->bulkRequest(['application_ids' => [$this->applicationId, $budi, $ani], 'check_type_id' => $typeId, 'place' => 'Klinik A'])->assertOk();

        $this->assertSame(
            [[$this->applicationId, 'skipped', 'check-already-open'], [$budi, 'moved', null], [$ani, 'skipped', 'check-not-allowed-stage']],
            array_map(fn (array $item): array => [$item['application_id'], $item['status'], $item['code']], $response->json('data')),
        );
        $this->assertSame(1, DB::table('recruitment_application_checks')->where('application_id', $budi)->where('place', 'Klinik A')->count());
        $this->assertSame(0, DB::table('recruitment_application_checks')->where('application_id', $ani)->count());

        // Each check keeps the single request's notification.
        $budiUser = (int) DB::table('recruitment_applications')->where('id', $budi)->value('user_id');
        $this->assertSame(['check.requested'], $this->checkNotifications($budiUser));
    }

    public function test_a_bulk_request_is_refused_as_a_whole_for_a_bad_selection_or_input(): void
    {
        [$budi] = $this->moreApplications();
        $typeId = (int) $this->type()->json('data.id');
        $otherJob = $this->insertJob('Data Analyst', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->moveToInterview($budi);

        $this->bulkRequest(['application_ids' => [$budi], 'check_type_id' => $typeId], job: $otherJob)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-applications');
        $this->bulkRequest(['application_ids' => [$budi, 999_999], 'check_type_id' => $typeId])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-applications');
        $this->bulkRequest(['application_ids' => [$budi], 'check_type_id' => 999_999])->assertNotFound();
        $this->bulkRequest(['application_ids' => [$budi], 'check_type_id' => $typeId, 'place' => str_repeat('a', 201)])->assertStatus(422);
        $this->bulkRequest(['application_ids' => [], 'check_type_id' => $typeId])->assertStatus(422);

        $this->api('POST', "/company/check-types/{$typeId}/deactivate", token: $this->recruiterToken)->assertSuccessful();
        $this->bulkRequest(['application_ids' => [$budi], 'check_type_id' => $typeId])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:check-type-inactive');

        $this->assertSame(0, DB::table('recruitment_application_checks')->where('application_id', $budi)->count());
    }

    public function test_a_bulk_request_needs_the_permission_and_the_switch(): void
    {
        [$budi] = $this->moreApplications();
        $typeId = (int) $this->type()->json('data.id');
        $this->moveToInterview($budi);
        $body = ['application_ids' => [$budi], 'check_type_id' => $typeId];

        $this->withoutChecksPermission();
        $this->bulkRequest($body, token: $this->secondRecruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-permission-required');

        $this->app->make(SettingsStore::class)->setGlobal('recruitment.pre_employment_checks_enabled', false, null);
        $this->bulkRequest($body)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:feature-disabled');
        $this->assertSame(0, DB::table('recruitment_application_checks')->count());
    }

    /** @return list<Email> */
    private function sentEmails(): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();

        return array_values(array_map(fn ($sent) => $sent->getOriginalMessage(), $transport->messages()->all()));
    }

    /** @return list<Email> the emails whose subject contains the text */
    private function emailsAbout(string $subject): array
    {
        return array_values(array_filter($this->sentEmails(), fn (Email $email): bool => str_contains($email->getSubject() ?? '', $subject)));
    }

    public function test_the_candidate_is_emailed_about_a_check_and_follows_the_preference(): void
    {
        $this->toStage('interview');
        $typeId = (int) $this->type()->json('data.id');

        $checkId = (int) $this->request($typeId, ['place' => 'Klinik A', 'vendor' => 'Klinik Sehat', 'note_to_candidate' => 'Bring your ID.'])->assertCreated()->json('data.id');
        $requested = $this->emailsAbout('MCU for your application');
        $this->assertCount(1, $requested);
        $body = (string) $requested[0]->getTextBody();
        $this->assertStringContainsString('Place: Klinik A', $body);
        $this->assertStringContainsString('Provided by: Klinik Sehat', $body);
        $this->assertStringContainsString('Note: Bring your ID.', $body);
        $this->assertStringContainsString('needs your consent', $body);

        $patch = fn (array $body) => $this->api('PATCH', "/company/applications/{$this->applicationId}/checks/{$checkId}", $body, $this->recruiterToken)->assertOk();
        $patch(['scheduled_at' => '2026-11-20T10:00:00+07:00', 'place' => 'Klinik A']);
        $this->assertStringContainsString('Time: 2026-11-20 10:00 WIB', (string) $this->emailsAbout('The time of your MCU')[0]->getTextBody());
        $patch(['scheduled_at' => '2026-11-21T10:00:00+07:00', 'place' => 'Klinik A']);
        $this->assertCount(1, $this->emailsAbout('The time of your MCU for Backend Developer changed'));

        $this->api('PUT', '/me/notification-preferences/application_update/email', ['enabled' => false], $this->candidateToken)->assertNoContent();
        $before = count($this->sentEmails());
        $this->api('POST', "/company/applications/{$this->applicationId}/checks/{$checkId}/cancel", token: $this->recruiterToken)->assertOk();
        $this->assertCount($before, $this->sentEmails());
        $this->assertContains('check.cancelled', $this->checkNotifications((int) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('user_id')));
    }

    public function test_the_candidate_is_emailed_when_a_check_is_cancelled_and_the_team_is_not_emailed_of_a_refusal(): void
    {
        $this->toStage('interview');
        $checkId = (int) $this->request((int) $this->type()->json('data.id'))->assertCreated()->json('data.id');
        $before = count($this->sentEmails());

        $this->api('POST', "/me/applications/{$this->applicationId}/checks/{$checkId}/refuse", ['reason' => 'No time'], $this->candidateToken)->assertNoContent();
        $this->assertCount($before, $this->sentEmails());

        $this->api('POST', "/company/applications/{$this->applicationId}/checks/{$checkId}/cancel", token: $this->recruiterToken)->assertOk();
        $this->assertCount(1, $this->emailsAbout('was cancelled'));
    }

    /** The portal identity of an installation: none is the marketplace, otherwise a company portal of one group or of one company. */
    public static function installations(): array
    {
        return ['marketplace' => [null, null], 'company portal of a group' => ['company', 'group'], 'company portal of one company' => ['company', 'company']];
    }

    #[DataProvider('installations')]
    public function test_the_checks_work_the_same_in_every_installation(?string $mode, ?string $scope): void
    {
        if ($mode !== null) {
            DB::table('platform_portal_identity')->insert(['mode' => $mode, 'scope' => $scope, 'company_ids' => json_encode([$this->companyId]), 'updated_at' => now()]);
        }

        $this->toStage('offer');
        $first = (int) $this->type(['name' => 'MCU'])->assertCreated()->json('data.id');
        $second = (int) $this->type(['name' => 'Verifikasi ijazah', 'kind' => 'education'])->assertCreated()->json('data.id');

        $this->request($first)->assertCreated();
        $this->sendOffer(['conditional_on_checks' => true, 'condition_check_type_ids' => [$first]])->assertOk()->assertJsonPath('data.conditional_on_checks', true);

        $job = (int) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('job_id');
        $this->bulkRequest(['application_ids' => [$this->applicationId], 'check_type_id' => $second], $job)->assertOk()->assertJsonPath('data.0.status', 'moved');
        $this->api('GET', "/company/applications/{$this->applicationId}/checks", token: $this->recruiterToken)->assertOk()->assertJsonCount(2, 'data');
    }
}
