<?php

namespace Tests\Feature\Assessment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * Licence renewals with the document (`docs/design/phase-8-provider-institutions.md` section 2): the psychologist submits a renewed STR or SILP, the platform admin
 * approves it (the number and the date go onto the psychologist) or rejects it with a note. The days are worked out from the clock the fixtures freeze.
 */
final class ProviderLicenceTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $psychologistId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();
        $this->psychologistId = (int) DB::table('assessment_psychologists')->where('provider_id', $this->providerId)->value('id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    /**
     * @param  array<string, mixed>  $fields
     */
    private function submit(array $fields = [], ?string $token = null, ?string $contents = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->psychologistToken)->post('/api/v1/provider/me/licences', [
            'kind' => 'str', 'number' => 'STR-NEW', 'valid_until' => '2031-03-31',
            'file' => UploadedFile::fake()->createWithContent('str.pdf', $contents ?? "%PDF-1.4\n%test"),
            ...$fields,
        ], ['Accept' => 'application/json']);
    }

    // ----- the psychologist -----

    public function test_a_psychologist_submits_a_renewal_with_the_document(): void
    {
        $this->submit()
            ->assertCreated()
            ->assertJsonPath('data.kind', 'str')
            ->assertJsonPath('data.number', 'STR-NEW')
            ->assertJsonPath('data.valid_until', '2031-03-31')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.document_name', 'str.pdf');

        $row = DB::table('assessment_licence_submissions')->first();
        $this->assertSame($this->psychologistId, (int) $row->psychologist_id);
        Storage::assertExists($row->document_path);

        // The licence in force does not change until the admin approves.
        $this->assertSame('STR-1', DB::table('assessment_psychologists')->where('id', $this->psychologistId)->value('str_number'));

        $this->provider('GET', 'me/licences')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'pending');
    }

    public function test_only_one_submission_of_a_licence_waits_at_a_time(): void
    {
        $this->submit()->assertCreated();

        $this->submit()->assertConflict()->assertJsonPath('type', 'urn:onesis:error:licence-pending');
        $this->submit(['kind' => 'silp', 'number' => 'SIPP-NEW'])->assertCreated();
        $this->assertSame(2, DB::table('assessment_licence_submissions')->count());
        $this->assertCount(2, Storage::allFiles('assessment/licences'));
    }

    public function test_the_document_is_checked_by_its_content_and_the_licence_must_still_be_valid(): void
    {
        $this->submit([], null, 'not a pdf at all')->assertUnprocessable()->assertJsonPath('type', 'urn:onesis:error:invalid-licence-document');
        $this->submit([], null, '%PDF-'.str_repeat('a', 10 * 1024 * 1024))->assertUnprocessable()->assertJsonPath('type', 'urn:onesis:error:file-too-large');
        $this->submit(['valid_until' => now()->subDay()->format('Y-m-d')])->assertUnprocessable()->assertJsonPath('type', 'urn:onesis:error:licence-already-expired');
        $this->submit(['valid_until' => now()->format('Y-m-d')])->assertCreated();

        $this->assertSame(1, DB::table('assessment_licence_submissions')->count());
        $this->assertCount(1, Storage::allFiles('assessment/licences'));
    }

    public function test_the_fields_are_validated(): void
    {
        $this->submit(['kind' => 'sim'])->assertUnprocessable();
        $this->submit(['number' => 'X'])->assertUnprocessable();
        $this->submit(['valid_until' => '31-03-2031'])->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->psychologistToken)->postJson('/api/v1/provider/me/licences', ['kind' => 'str', 'number' => 'STR-NEW', 'valid_until' => '2031-03-31'])->assertUnprocessable();
    }

    public function test_a_psychologist_sees_only_their_own_submissions(): void
    {
        $this->submit()->assertCreated();

        $this->provider('GET', 'me/licences', [], $this->otherPsychologistToken)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_endpoints_need_a_signed_in_psychologist(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withoutToken()->getJson('/api/v1/provider/me/licences')->assertUnauthorized();
    }

    // ----- the platform admin -----

    public function test_the_admin_approves_and_the_number_and_date_go_onto_the_psychologist(): void
    {
        $id = (int) $this->submit()->assertCreated()->json('data.id');

        $this->assessmentAdmin('GET', 'licence-submissions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.psychologist_id', $this->psychologistId)
            ->assertJsonPath('data.0.psychologist_name', 'Psikolog Lembaga Psikologi Contoh')
            ->assertJsonPath('data.0.provider_name', 'Lembaga Psikologi Contoh')
            ->assertJsonPath('meta.total', 1);

        $this->assessmentAdmin('POST', "licence-submissions/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

        $psychologist = DB::table('assessment_psychologists')->where('id', $this->psychologistId)->first();
        $this->assertSame('STR-NEW', $psychologist->str_number);
        $this->assertSame('2031-03-31', substr((string) $psychologist->str_valid_until, 0, 10));
        $this->assertSame('SIPP-1', $psychologist->silp_number);

        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.licence_approved', 'actor_type' => 'admin', 'subject_id' => $id]);
        $this->assertDatabaseHas('notifications', ['notifiable_type' => 'user', 'notifiable_id' => $psychologist->user_id, 'type' => 'assessment.licence_approved']);
        $this->assessmentAdmin('POST', "licence-submissions/{$id}/approve")->assertConflict()->assertJsonPath('type', 'urn:onesis:error:licence-decided');
    }

    public function test_a_rejection_needs_a_note_and_leaves_the_licence_alone(): void
    {
        $id = (int) $this->submit()->assertCreated()->json('data.id');

        $this->assessmentAdmin('POST', "licence-submissions/{$id}/reject", [])->assertUnprocessable();
        $this->assessmentAdmin('POST', "licence-submissions/{$id}/reject", ['note' => 'Dokumen tidak terbaca.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.decision_note', 'Dokumen tidak terbaca.');

        $this->assertSame('STR-1', DB::table('assessment_psychologists')->where('id', $this->psychologistId)->value('str_number'));
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assessment.licence_rejected', 'subject_id' => $id]);
        $this->assessmentAdmin('POST', "licence-submissions/{$id}/reject", ['note' => 'Sekali lagi.'])->assertConflict();

        // The psychologist may submit again after a rejection, and reads the note.
        $this->provider('GET', 'me/licences')->assertOk()->assertJsonPath('data.0.decision_note', 'Dokumen tidak terbaca.');
        $this->submit()->assertCreated();
    }

    public function test_the_queue_lists_the_pending_ones_first_and_can_be_filtered(): void
    {
        $first = (int) $this->submit()->assertCreated()->json('data.id');
        $this->assessmentAdmin('POST', "licence-submissions/{$first}/approve")->assertOk();
        $second = (int) $this->submit(['kind' => 'silp', 'number' => 'SIPP-NEW'])->assertCreated()->json('data.id');

        $this->assessmentAdmin('GET', 'licence-submissions')->assertOk()->assertJsonPath('data.0.id', $second)->assertJsonPath('data.1.id', $first);
        $this->assessmentAdmin('GET', 'licence-submissions?filter[status]=approved')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first);
        $this->assessmentAdmin('GET', 'licence-submissions?filter[status]=other')->assertUnprocessable();
    }

    public function test_the_admin_reads_the_document_as_a_download_nobody_else_can(): void
    {
        $id = (int) $this->submit()->assertCreated()->json('data.id');

        $this->assessmentAdmin('GET', "licence-submissions/{$id}/document")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assessmentAdmin('GET', 'licence-submissions/999999/document')->assertNotFound();

        $this->api('GET', "/admin/assessment/licence-submissions/{$id}/document", [], $this->psychologistToken)->assertUnauthorized();
    }

    public function test_an_admin_without_the_permission_is_refused(): void
    {
        $this->submit()->assertCreated();
        $limited = AdminModel::query()->create(['name' => 'Pustakawan', 'email' => 'library@example.test', 'password' => 'hash', 'status' => 'active']);
        $limited->givePermissionTo(['assessment_library.manage']);
        $token = $limited->createToken('console', ['*'], now()->addHour())->plainTextToken;

        $this->assessmentAdmin('GET', 'licence-submissions', [], $token)->assertForbidden();
        $this->assessmentAdmin('POST', 'licence-submissions/1/approve', [], $token)->assertForbidden();
    }
}
