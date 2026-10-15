<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Job\Application\JobSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-platform-admin.md` section 2: the jobs of an unverified company and the jobs with a suspicious text go to review. */
final class JobReviewPolicyTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    private string $opsToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
        // These tests are about the rule, so it applies (the other job tests switch it off for the unverified companies they build).
        $this->unverifiedReview = true;

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['jobs.manage']);
        $this->opsToken = $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    private function verify(bool $verified = true): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['verified_at' => $verified ? '2026-09-01 00:00:00' : null]);
    }

    /** @param  array<string, mixed>  $override */
    private function draft(string $title = 'Senior Backend Developer', array $override = []): int
    {
        return (int) $this->api('POST', '/company/jobs', [...$this->fullJobBody($title), ...$override], $this->adminToken)->assertCreated()->json('data.id');
    }

    private function publish(int $id): TestResponse
    {
        return $this->api('POST', "/company/jobs/{$id}/publish", [], $this->adminToken)->assertOk();
    }

    private function admin(string $method, string $uri): TestResponse
    {
        return $this->api($method, '/admin/jobs'.$uri, [], $this->opsToken);
    }

    private function setting(string $key, mixed $value): void
    {
        $this->app->make(SettingsStore::class)->setGlobal($key, $value, null);
    }

    // ----- an unverified company -----

    public function test_the_jobs_of_an_unverified_company_go_to_review_and_those_of_a_verified_one_go_live(): void
    {
        $this->publish($this->draft('First'))->assertJsonPath('data.status', 'pending_review');
        $this->assertNull(DB::table('job_jobs')->where('title', 'First')->value('published_at'));

        $this->verify();
        $this->publish($this->draft('Second'))->assertJsonPath('data.status', 'active');
    }

    public function test_the_admin_can_let_unverified_companies_publish_directly(): void
    {
        $this->setting(JobSettings::APPROVAL_FOR_UNVERIFIED, false);

        $this->publish($this->draft())->assertJsonPath('data.status', 'active');
    }

    public function test_a_verified_company_is_still_reviewed_when_its_own_setting_requires_approval(): void
    {
        $this->verify();
        $this->app->make(SettingsStore::class)->setCompanyOverride(JobSettings::REQUIRE_APPROVAL, $this->companyId, true, null);

        $this->publish($this->draft())->assertJsonPath('data.status', 'pending_review');
    }

    public function test_reopening_a_job_of_an_unverified_company_goes_to_review(): void
    {
        $this->verify();
        $id = $this->draft();
        $this->publish($id);
        $this->api('POST', "/company/jobs/{$id}/close", [], $this->adminToken)->assertOk();
        $this->verify(false);

        $this->api('POST', "/company/jobs/{$id}/reopen", ['deadline' => '2026-11-30'], $this->adminToken)->assertOk()->assertJsonPath('data.status', 'pending_review');
    }

    public function test_a_core_change_of_a_live_job_of_an_unverified_company_goes_back_to_review_and_another_change_does_not(): void
    {
        $this->verify();
        $id = $this->draft();
        $this->publish($id);
        $this->verify(false);

        $this->api('PATCH', "/company/jobs/{$id}", ['vacancies' => 9], $this->adminToken)->assertOk()->assertJsonPath('data.status', 'active');
        $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'A new title'], $this->adminToken)->assertOk()->assertJsonPath('data.status', 'pending_review');

        $entry = DB::table('platform_audit_logs')->where('action', 'job.sent_for_review')->first();
        $this->assertSame(['from' => 'active', 'to' => 'pending_review'], json_decode((string) $entry->metadata, true));
    }

    // ----- suspicious text -----

    public function test_a_phrase_of_the_list_sends_the_job_of_a_verified_company_to_review_whatever_the_letter_case(): void
    {
        $this->verify();

        $this->publish($this->draft('In the title', ['title' => 'Staff, BIAYA PENDAFTARAN Rp 50.000']))->assertJsonPath('data.status', 'pending_review');
        $this->publish($this->draft('In the description', ['description' => 'Kami membuka lowongan. Ada Biaya  Seragam yang harus dibayar di muka.']))->assertJsonPath('data.status', 'pending_review');
        $this->publish($this->draft('Clean'))->assertJsonPath('data.status', 'active');
    }

    public function test_a_phrase_matches_whole_words_only(): void
    {
        $this->verify();

        $this->publish($this->draft('Whole words', ['description' => 'Kami menyediakan depositories dan biaya pendaftarannya gratis untuk semua pelamar.']))->assertJsonPath('data.status', 'active');
    }

    public function test_the_admin_changes_the_phrases(): void
    {
        $this->verify();
        $this->setting(JobSettings::SUSPICIOUS_PHRASES, "gaji tinggi\n\n  lolos pasti  ");

        $this->publish($this->draft('Old phrase', ['description' => 'Biaya pendaftaran tidak ada, semua gratis untuk pelamar yang melamar.']))->assertJsonPath('data.status', 'active');
        $this->publish($this->draft('New phrase', ['description' => 'Gaji tinggi dan Lolos Pasti untuk semua pelamar yang melamar di sini.']))->assertJsonPath('data.status', 'pending_review');
    }

    public function test_a_phone_number_or_an_email_address_in_the_description_sends_the_job_to_review(): void
    {
        $this->verify();

        foreach (['Hubungi 0812-3456-7890 untuk info.', 'Hubungi +62 812 3456 7890 untuk info.', 'Telepon 021-5551234 untuk info.', 'Kirim lamaran ke hr@perusahaan.test sekarang.'] as $index => $text) {
            $this->publish($this->draft("With contact {$index}", ['description' => $text.' Kami membangun portal kerja.']))->assertJsonPath('data.status', 'pending_review');
        }

        $this->publish($this->draft('Plain numbers', ['description' => 'Kami memiliki 120 karyawan dan 3 kantor, gaji 10000000 sampai 15000000 per bulan.']))->assertJsonPath('data.status', 'active');
    }

    public function test_a_core_change_that_adds_a_phrase_sends_a_live_job_of_a_verified_company_back_to_review(): void
    {
        $this->verify();
        $id = $this->draft();
        $this->publish($id);

        $this->api('PATCH', "/company/jobs/{$id}", ['description' => 'Kami membuka lowongan. Ada uang jaminan yang harus dibayar di muka.'], $this->adminToken)
            ->assertOk()->assertJsonPath('data.status', 'pending_review');
    }

    // ----- what the admin sees -----

    public function test_the_admin_reads_the_flags_and_whether_the_company_is_verified(): void
    {
        $id = $this->insertJob('Admin staff', 'pending_review', attributes: [
            'deadline' => '2026-12-31', 'description' => 'Biaya pendaftaran Rp 100000, hubungi 0812-3456-7890 atau hr@scam.test untuk info.',
        ]);

        $detail = $this->admin('GET', "/{$id}")->assertOk()->json('data');

        $this->assertFalse($detail['company_verified']);
        $this->assertSame([
            ['kind' => 'phrase', 'value' => 'biaya pendaftaran', 'field' => 'description'],
            ['kind' => 'contact', 'value' => 'hr@scam.test', 'field' => 'description'],
            ['kind' => 'contact', 'value' => '0812-3456-7890', 'field' => 'description'],
        ], $detail['flags']);

        $this->verify();
        $clean = $this->insertJob('Clean one', 'pending_review', attributes: ['deadline' => '2026-12-31', 'description' => 'Kami membangun portal kerja yang baru.']);
        $other = $this->admin('GET', "/{$clean}")->assertOk()->json('data');
        $this->assertTrue($other['company_verified']);
        $this->assertSame([], $other['flags']);
    }

    public function test_the_review_queue_lists_the_flagged_jobs_first(): void
    {
        $clean = $this->insertJob('Clean', 'pending_review', attributes: ['updated_at' => '2026-10-04 09:00:00', 'description' => 'Kami membangun portal kerja yang baru.']);
        $flagged = $this->insertJob('Flagged', 'pending_review', attributes: ['updated_at' => '2026-10-01 09:00:00', 'description' => 'Ada deposit yang harus dibayar.']);
        $active = $this->insertJob('Live', 'active', attributes: ['updated_at' => '2026-10-03 09:00:00', 'description' => 'Ada deposit yang harus dibayar.']);

        $queue = $this->admin('GET', '?filter[status]=pending_review')->assertOk()->json('data');
        $this->assertSame([$flagged, $clean], array_column($queue, 'id'));
        $this->assertSame([true, false], array_column($queue, 'flagged'));

        $all = collect($this->admin('GET', '')->assertOk()->json('data'));
        $this->assertSame([$clean, $active, $flagged], $all->pluck('id')->all(), 'outside the queue the order is the plain sort');
        $this->assertSame([null, null, null], $all->pluck('flagged')->all());
    }
}
