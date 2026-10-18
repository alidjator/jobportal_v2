<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\EraseApplicantData;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-offer-letters.md` sections 1 and 2: the terms of an offer, the labour-law checks and the letter PDF (generated or the company's own). */
final class OfferLettersTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('offer'), 'offer' => $this->offerDetails()], $this->recruiterToken)->assertOk();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function send(array $overrides = [], ?int $applicationId = null): TestResponse
    {
        return $this->api('PUT', '/company/applications/'.($applicationId ?? $this->applicationId).'/offer', $this->offerDetails($overrides), $this->recruiterToken);
    }

    private function letterUpload(string $contents, ?int $applicationId = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->recruiterToken)->post(
            '/api/v1/company/applications/'.($applicationId ?? $this->applicationId).'/offer-letter-uploads',
            ['file' => UploadedFile::fake()->createWithContent('letter.pdf', $contents)],
            ['Accept' => 'application/json'],
        );
    }

    /** The application of a second, complete job seeker to the same job. */
    private function secondApplication(): int
    {
        $token = $this->tokenFor($this->seeker('second@example.test', name: 'Second Person'));
        $resumeId = $this->completeProfile($token);

        return (int) $this->apply($this->job, ['resume_id' => $resumeId], $token)->assertCreated()->json('data.id');
    }

    private function offerRow(?int $offerId = null): object
    {
        $query = DB::table('recruitment_offers')->where('application_id', $this->applicationId);

        return $offerId === null ? $query->orderByDesc('id')->firstOrFail() : $query->where('id', $offerId)->firstOrFail();
    }

    // ----- the terms -----

    public function test_the_offer_carries_its_terms(): void
    {
        $health = $this->benefitId('health-insurance');
        $bonus = $this->benefitId('annual-bonus');

        $response = $this->send([
            'employment_type' => 'contract', 'contract_months' => 12, 'probation_months' => null, 'work_mode' => 'remote', 'work_location' => null, 'salary_basis' => 'net',
            'allowances' => [['name' => 'Transport', 'amount' => 500_000], ['name' => 'Meal', 'amount' => 750_000]], 'benefit_ids' => [$health, $bonus, $health],
        ])->assertOk();

        $response->assertJsonPath('data.terms.employment_type', 'contract')
            ->assertJsonPath('data.terms.contract_months', 12)
            ->assertJsonPath('data.terms.probation_months', null)
            ->assertJsonPath('data.terms.work_mode', 'remote')
            ->assertJsonPath('data.terms.work_location', null)
            ->assertJsonPath('data.terms.salary_basis', 'net')
            ->assertJsonPath('data.terms.allowances_total', 1_250_000)
            ->assertJsonPath('data.terms.allowances.1', ['name' => 'Meal', 'amount' => 750_000])
            ->assertJsonPath('data.terms.benefits.0.id', $health)
            ->assertJsonPath('data.terms.benefits.0.name.en', 'health-insurance')
            ->assertJsonPath('data.terms.benefits.1.id', $bonus)
            ->assertJsonPath('data.terms.benefits.1.name.id', 'annual-bonus');

        $listed = $this->api('GET', "/company/applications/{$this->applicationId}/offers", token: $this->recruiterToken)->assertOk();
        $listed->assertJsonPath('data.0.terms.employment_type', 'contract');
        $this->api('GET', "/me/applications/{$this->applicationId}/offer", token: $this->seekerToken)->assertOk()->assertJsonPath('data.terms.work_mode', 'remote');
    }

    public function test_the_move_request_carries_the_terms_too(): void
    {
        $row = $this->offerRow();

        $this->assertSame('permanent', $row->employment_type);
        $this->assertSame(3, (int) $row->probation_months);
        $this->assertSame('Jakarta Selatan', $row->work_location);
        $this->assertSame([['name' => 'Transport', 'amount' => 500000]], json_decode($row->allowances, true));
        $this->assertSame([], json_decode($row->benefits, true));
    }

    public function test_the_employment_type_the_work_mode_and_the_salary_basis_are_required(): void
    {
        foreach (['employment_type', 'work_mode', 'salary_basis'] as $field) {
            $body = $this->offerDetails();
            unset($body[$field]);

            $this->api('PUT', "/company/applications/{$this->applicationId}/offer", $body, $this->recruiterToken)->assertStatus(422)->assertJsonStructure(['errors' => [$field]]);
        }

        $this->send(['employment_type' => 'seasonal'])->assertStatus(422)->assertJsonStructure(['errors' => ['employment_type']]);
        $this->send(['work_mode' => 'moon'])->assertStatus(422)->assertJsonStructure(['errors' => ['work_mode']]);
        $this->send(['allowances' => [['name' => 'Transport']]])->assertStatus(422);
    }

    public function test_the_field_limits_are_checked(): void
    {
        $this->send(['employment_type' => 'contract'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer')->assertJsonStructure(['errors' => ['contract_months']]);
        $this->send(['contract_months' => 12])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer');
        $this->send(['work_location' => null])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer')->assertJsonStructure(['errors' => ['work_location']]);
        $this->send(['work_mode' => 'remote', 'work_location' => null])->assertOk();
        $this->send(['allowances' => array_fill(0, 11, ['name' => 'Transport', 'amount' => 1000])])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer');
        $this->send(['allowances' => [['name' => 'Bonus', 'amount' => 0]]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer');
        $this->send(['benefit_ids' => [999_999]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer')->assertJsonStructure(['errors' => ['benefit_ids']]);
    }

    public function test_the_labour_rules_are_checked_while_the_setting_is_on(): void
    {
        $this->send(['probation_months' => 4])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:probation-too-long');
        $this->send(['employment_type' => 'contract', 'contract_months' => 12, 'probation_months' => 2])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:probation-not-allowed');
        $this->send(['employment_type' => 'internship', 'contract_months' => 13, 'probation_months' => null])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:contract-too-long');
        $this->send(['employment_type' => 'internship', 'contract_months' => 12, 'probation_months' => null])->assertOk();
    }

    public function test_the_labour_rules_can_be_turned_off_globally_or_for_one_company(): void
    {
        $store = $this->app->make(SettingsStore::class);

        $store->setCompanyOverride('recruitment.offer_labor_rules', $this->companyId, false, null);
        $this->send(['employment_type' => 'contract', 'contract_months' => 24, 'probation_months' => 6])->assertOk()->assertJsonPath('data.terms.probation_months', 6);
        $this->send(['probation_months' => 13])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer');

        $store->setCompanyOverride('recruitment.offer_labor_rules', $this->companyId, true, null);
        $this->send(['probation_months' => 6])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:probation-too-long');

        $store->setGlobal('recruitment.offer_labor_rules', false, null);
        $store->removeCompanyOverride('recruitment.offer_labor_rules', $this->companyId);
        $this->send(['probation_months' => 6])->assertOk();
    }

    public function test_an_offer_sent_before_the_terms_existed_shows_none(): void
    {
        DB::table('recruitment_offers')->where('application_id', $this->applicationId)->update([
            'employment_type' => null, 'work_mode' => null, 'salary_basis' => null, 'probation_months' => null, 'work_location' => null, 'allowances' => null, 'benefits' => null,
            'letter_path' => null, 'letter_source' => null, 'letter_sha256' => null,
        ]);
        $id = (int) $this->offerRow()->id;

        $this->api('GET', "/company/applications/{$this->applicationId}/offers", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.0.terms', null)->assertJsonPath('data.0.letter', null);
        $this->api('GET', "/company/applications/{$this->applicationId}/offers/{$id}/letter.pdf", token: $this->recruiterToken)->assertNotFound();
        $this->api('GET', "/me/applications/{$this->applicationId}/offer/letter.pdf", token: $this->seekerToken)->assertNotFound();
    }

    public function test_the_email_preview_of_an_offer_needs_the_terms_too(): void
    {
        $body = ['scenario' => 'offer_sent', ...$this->offerDetails()];
        unset($body['employment_type']);

        $this->api('POST', "/company/applications/{$this->applicationId}/email-preview", $body, $this->recruiterToken)->assertStatus(422)->assertJsonStructure(['errors' => ['employment_type']]);
    }

    // ----- the generated letter -----

    public function test_the_platform_generates_the_letter_when_the_offer_is_sent(): void
    {
        $row = $this->offerRow();

        $this->assertSame('generated', $row->letter_source);
        $this->assertStringStartsWith("recruitment/offers/{$row->id}/", $row->letter_path);
        $contents = Storage::get($row->letter_path);
        $this->assertStringStartsWith('%PDF-', $contents);
        $this->assertSame(hash('sha256', $contents), $row->letter_sha256);

        $this->api('GET', "/company/applications/{$this->applicationId}/offers", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.0.letter', ['source' => 'generated', 'sha256' => $row->letter_sha256]);
    }

    public function test_a_revision_gets_its_own_letter_and_the_earlier_one_stays(): void
    {
        $first = $this->offerRow();

        $this->send(['salary' => 13_000_000])->assertOk();
        $second = $this->offerRow();

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->letter_path, $second->letter_path);
        Storage::assertExists($first->letter_path);
        Storage::assertExists($second->letter_path);
    }

    public function test_a_company_member_downloads_the_letter_and_the_download_is_audited(): void
    {
        $row = $this->offerRow();

        $response = $this->api('GET', "/company/applications/{$this->applicationId}/offers/{$row->id}/letter.pdf", token: $this->recruiterToken)->assertOk();

        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame($row->letter_sha256, hash('sha256', $response->getContent()));

        $entry = DB::table('platform_audit_logs')->where('action', 'offer.letter_downloaded')->first();
        $this->assertNotNull($entry);
        $this->assertSame('offer', $entry->subject_type);
        $this->assertSame((int) $row->id, (int) $entry->subject_id);
        $this->assertSame($this->companyId, (int) $entry->company_id);
    }

    public function test_the_team_letter_belongs_to_its_application_and_its_company(): void
    {
        $row = $this->offerRow();

        $this->api('GET', "/company/applications/{$this->applicationId}/offers/999999/letter.pdf", token: $this->recruiterToken)->assertNotFound();
        $this->api('GET', "/company/applications/999999/offers/{$row->id}/letter.pdf", token: $this->recruiterToken)->assertNotFound();
        $this->api('GET', "/company/applications/{$this->applicationId}/offers/{$row->id}/letter.pdf", token: $this->seekerToken)->assertForbidden();

        Storage::delete($row->letter_path);
        $this->api('GET', "/company/applications/{$this->applicationId}/offers/{$row->id}/letter.pdf", token: $this->recruiterToken)->assertNotFound();
    }

    public function test_the_job_seeker_downloads_the_letter_of_their_pending_offer_only(): void
    {
        $row = $this->offerRow();

        $response = $this->api('GET', "/me/applications/{$this->applicationId}/offer/letter.pdf", token: $this->seekerToken)->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($row->letter_sha256, hash('sha256', $response->getContent()));

        $other = $this->tokenFor($this->seeker('other@example.test', name: 'Other Person'));
        $this->api('GET', "/me/applications/{$this->applicationId}/offer/letter.pdf", token: $other)->assertNotFound();

        $this->api('POST', "/company/applications/{$this->applicationId}/offer/withdraw", token: $this->recruiterToken)->assertOk();
        $this->api('GET', "/me/applications/{$this->applicationId}/offer/letter.pdf", token: $this->seekerToken)->assertNotFound();
    }

    public function test_the_accepted_letter_stays_readable_for_the_job_seeker(): void
    {
        $this->api('POST', "/me/applications/{$this->applicationId}/offer/accept", token: $this->seekerToken)->assertOk();

        $this->api('GET', "/me/applications/{$this->applicationId}/offer/letter.pdf", token: $this->seekerToken)->assertOk();
    }

    // ----- the company's own letter -----

    public function test_the_company_uploads_its_own_letter_and_the_offer_uses_it(): void
    {
        $contents = $this->pdf();
        $upload = $this->letterUpload($contents)->assertCreated();
        $upload->assertJsonPath('data.sha256', hash('sha256', $contents))->assertJsonPath('data.size_bytes', strlen($contents));
        $uploadId = (int) $upload->json('data.upload_id');
        $uploaded = (string) DB::table('recruitment_offer_letter_uploads')->where('id', $uploadId)->value('path');
        Storage::assertExists($uploaded);

        $this->send(['letter_upload_id' => $uploadId])->assertOk()->assertJsonPath('data.letter', ['source' => 'uploaded', 'sha256' => hash('sha256', $contents)]);

        $row = $this->offerRow();
        $this->assertSame($contents, Storage::get($row->letter_path));
        $this->assertStringStartsWith("recruitment/offers/{$row->id}/", $row->letter_path);
        $this->assertSame(0, DB::table('recruitment_offer_letter_uploads')->count());
        Storage::assertMissing($uploaded);

        $this->send(['letter_upload_id' => $uploadId])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer')->assertJsonStructure(['errors' => ['letter_upload_id']]);
    }

    public function test_the_upload_can_come_with_the_move_to_the_offer_stage(): void
    {
        $other = $this->secondApplication();
        $uploadId = (int) $this->letterUpload($this->pdf(), $other)->assertCreated()->json('data.upload_id');

        $this->api('POST', "/company/applications/{$other}/move", ['stage_id' => $this->stage('offer'), 'offer' => $this->offerDetails(['letter_upload_id' => $uploadId])], $this->recruiterToken)->assertOk();

        $this->assertSame('uploaded', DB::table('recruitment_offers')->where('application_id', $other)->value('letter_source'));
    }

    public function test_only_a_pdf_of_at_most_ten_megabytes_is_accepted(): void
    {
        $this->letterUpload('GIF89a not a pdf')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-letter');
        $this->letterUpload("\x89PNG\r\n\x1a\n")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-letter');
        $this->letterUpload('%PDF-1.4'.str_repeat('a', 10 * 1024 * 1024))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:file-too-large');
        $this->assertSame(0, DB::table('recruitment_offer_letter_uploads')->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($this->recruiterToken)->postJson("/api/v1/company/applications/{$this->applicationId}/offer-letter-uploads", [])->assertStatus(422)->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_an_upload_is_for_one_application_and_one_company(): void
    {
        $other = $this->secondApplication();
        $uploadId = (int) $this->letterUpload($this->pdf(), $other)->assertCreated()->json('data.upload_id');

        $this->send(['letter_upload_id' => $uploadId])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->seekerToken)->post("/api/v1/company/applications/{$this->applicationId}/offer-letter-uploads", ['file' => UploadedFile::fake()->createWithContent('letter.pdf', $this->pdf())], ['Accept' => 'application/json'])->assertForbidden();
        $this->letterUpload($this->pdf(), 999_999)->assertNotFound();
    }

    public function test_an_upload_expires_after_24_hours_and_the_daily_purge_deletes_it(): void
    {
        $old = (int) $this->letterUpload($this->pdf())->assertCreated()->json('data.upload_id');
        $oldPath = (string) DB::table('recruitment_offer_letter_uploads')->where('id', $old)->value('path');

        DB::table('recruitment_offer_letter_uploads')->where('id', $old)->update(['created_at' => now()->subHours(25)]);
        $fresh = (int) $this->letterUpload($this->pdf())->assertCreated()->json('data.upload_id');
        $this->send(['letter_upload_id' => $old, 'expires_at' => now()->addDays(5)->utc()->format('Y-m-d\TH:i:s\Z')])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer');

        $this->assertSame(0, Artisan::call('recruitment:purge-offer-letter-uploads'));

        $this->assertSame([$fresh], DB::table('recruitment_offer_letter_uploads')->pluck('id')->map(fn ($id): int => (int) $id)->all());
        Storage::assertMissing($oldPath);
    }

    public function test_a_bulk_move_cannot_carry_one_company_letter(): void
    {
        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", [
            'application_ids' => [$this->applicationId], 'stage_id' => $this->stage('offer'), 'offer' => $this->offerDetails(['letter_upload_id' => 1]),
        ], $this->recruiterToken)->assertStatus(422)->assertJsonStructure(['errors' => ['offer.letter_upload_id']]);
    }

    public function test_erasing_the_applicant_deletes_their_letters_and_waiting_uploads(): void
    {
        $row = $this->offerRow();
        $uploadId = (int) $this->letterUpload($this->pdf())->assertCreated()->json('data.upload_id');
        $uploaded = (string) DB::table('recruitment_offer_letter_uploads')->where('id', $uploadId)->value('path');

        $this->app->make(EraseApplicantData::class)->execute($this->seekerId);

        Storage::assertMissing($row->letter_path);
        Storage::assertMissing($uploaded);
        $this->assertSame(0, DB::table('recruitment_offer_letter_uploads')->count());
        $this->assertSame(0, DB::table('recruitment_offers')->count());
    }
}
