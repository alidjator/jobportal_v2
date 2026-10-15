<?php

namespace Tests\Feature\JobSeeker;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\JobSeeker\Application\EraseJobSeekerData;
use Src\JobSeeker\Infrastructure\Mail\TemplatedMail;
use Src\Shared\Application\Audit\AuditAction;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use ZipArchive;

final class DataExportTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private int $userId;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        Storage::fake();
        Mail::fake();
        $this->userId = $this->account('job_seeker', 'siti@example.test');
        $this->token = $this->tokenFor($this->userId);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function account(string $type, string $email): int
    {
        return (int) UserModel::query()->create(['type' => $type, 'name' => 'Siti', 'email' => $email, 'status' => 'active', 'locale' => 'id', 'email_verified_at' => '2026-10-01 00:00:00'])->id;
    }

    private function tokenFor(int $userId): string
    {
        return UserModel::query()->findOrFail($userId)->createToken('phone', ['*'], now()->addDays(10))->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1'.$uri, $body);
    }

    /** @return array{data: array<string, mixed>, readme: string, files: list<string>} */
    private function openArchive(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'test-export');
        file_put_contents($path, $response->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));

        $files = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $files[] = (string) $zip->getNameIndex($i);
        }

        $archive = ['data' => json_decode((string) $zip->getFromName('data.json'), true, flags: JSON_THROW_ON_ERROR), 'readme' => (string) $zip->getFromName('README.txt'), 'files' => $files];
        $zip->close();
        unlink($path);

        return $archive;
    }

    public function test_the_export_belongs_to_a_signed_in_job_seeker(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/me/data-export')->assertUnauthorized();

        $member = $this->tokenFor($this->account('company_member', 'budi@example.test'));
        $this->api('POST', '/me/data-export', token: $member)->assertForbidden()->assertJson(['type' => 'urn:jobportal:error:job-seeker-only']);
        $this->api('GET', '/me/data-export', token: $member)->assertForbidden();
    }

    public function test_nothing_was_asked_for_yet(): void
    {
        $this->api('GET', '/me/data-export')->assertNotFound();
    }

    public function test_a_request_builds_the_archive_and_the_person_downloads_it(): void
    {
        DB::table('jobseeker_profiles')->insert(['user_id' => $this->userId, 'phone' => '081234567890', 'headline' => 'Backend developer', ...$this->locationChain(), 'created_at' => now(), 'updated_at' => now()]);
        Storage::put('jobseeker/resumes/cv.pdf', "%PDF-1.4\nfake\n%%EOF\n");
        DB::table('jobseeker_resumes')->insert(['user_id' => $this->userId, 'name' => 'My CV', 'path' => 'jobseeker/resumes/cv.pdf', 'size_bytes' => 20, 'created_at' => now()]);

        $this->api('POST', '/me/data-export')->assertStatus(202)->assertJsonPath('data.status', 'building');

        $status = $this->api('GET', '/me/data-export')->assertOk();
        $status->assertJsonPath('data.status', 'ready')->assertJsonPath('data.expires_at', '2026-10-06T09:00:00+00:00');
        $this->assertStringEndsWith('/api/v1/me/data-export/download', (string) $status->json('data.download_url'));

        $download = $this->api('GET', '/me/data-export/download')->assertOk();
        $this->assertSame('application/zip', $download->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));

        $archive = $this->openArchive($download);

        $this->assertSame(1, $archive['data']['version']);
        $this->assertSame('siti@example.test', $archive['data']['account']['email']);
        $this->assertSame('Backend developer', $archive['data']['profile']['details']['headline']);
        $this->assertSame('JAWA BARAT', $archive['data']['profile']['details']['province']);
        $this->assertSame('My CV', $archive['data']['profile']['resumes'][0]['name']);
        $resumeId = (int) DB::table('jobseeker_resumes')->value('id');
        $this->assertContains("files/profile/resume-{$resumeId}.pdf", $archive['files']);
        $this->assertContains('data.json', $archive['files']);
        $this->assertStringContainsString('UU No. 27 Tahun 2022', $archive['readme']);

        foreach (['account', 'profile', 'applications', 'assessments', 'job_invitations', 'saved_jobs', 'job_alerts', 'conversations', 'reviews', 'notification_preferences', 'talent_pool'] as $key) {
            $this->assertArrayHasKey($key, $archive['data'], "the part {$key} is exported");
        }
    }

    public function test_the_archive_holds_no_secret_and_nothing_of_another_account(): void
    {
        $other = $this->account('job_seeker', 'other@example.test');
        DB::table('jobseeker_profiles')->insert(['user_id' => $other, 'phone' => '089999999999', 'created_at' => now(), 'updated_at' => now()]);

        $this->api('POST', '/me/data-export')->assertStatus(202);
        $json = json_encode($this->openArchive($this->api('GET', '/me/data-export/download'))['data']);

        $this->assertStringNotContainsString('other@example.test', (string) $json);
        $this->assertStringNotContainsString('089999999999', (string) $json);
        $this->assertStringNotContainsString('"password"', (string) $json);
    }

    public function test_the_ready_notice_is_in_the_app_and_by_email_and_the_export_is_audited(): void
    {
        $this->api('POST', '/me/data-export')->assertStatus(202);

        $this->assertSame(1, DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $this->userId)->where('type', 'account.data_export_ready')->count());
        Mail::assertQueued(TemplatedMail::class, 1);
        Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail): bool => $mail->hasTo('siti@example.test') && $mail->queue === 'default');

        $audit = DB::table('platform_audit_logs')->where('action', AuditAction::PRIVACY_DATA_EXPORTED)->first();
        $this->assertNotNull($audit);
        $this->assertSame($this->userId, (int) $audit->actor_id);
        $this->assertStringNotContainsString('siti@example.test', (string) json_encode($audit));
    }

    public function test_a_second_request_within_a_day_is_refused_with_the_time_to_wait(): void
    {
        $this->api('POST', '/me/data-export')->assertStatus(202);

        Carbon::setTestNow('2026-10-05 15:00:00');
        $response = $this->api('POST', '/me/data-export')->assertStatus(429)->assertJson(['type' => 'urn:jobportal:error:data-export-limit']);
        $this->assertSame((string) (18 * 3600), $response->headers->get('Retry-After'));

        Carbon::setTestNow('2026-10-06 09:00:01');
        $this->api('POST', '/me/data-export')->assertStatus(202);
        $this->assertSame(2, DB::table('jobseeker_data_exports')->where('user_id', $this->userId)->count());
    }

    public function test_a_build_that_is_still_running_answers_409(): void
    {
        DB::table('jobseeker_data_exports')->insert(['user_id' => $this->userId, 'status' => 'building', 'created_at' => '2026-10-05 08:50:00', 'updated_at' => '2026-10-05 08:50:00']);

        $this->api('POST', '/me/data-export')->assertStatus(409)->assertJson(['type' => 'urn:jobportal:error:data-export-running']);
    }

    public function test_a_failed_request_does_not_count_against_the_daily_limit(): void
    {
        DB::table('jobseeker_data_exports')->insert(['user_id' => $this->userId, 'status' => 'failed', 'error' => 'The build failed.', 'created_at' => '2026-10-05 08:00:00', 'updated_at' => '2026-10-05 08:00:00']);

        $this->api('POST', '/me/data-export')->assertStatus(202);
        $this->api('GET', '/me/data-export')->assertJsonPath('data.status', 'ready');
    }

    public function test_the_cleanup_deletes_the_archive_after_a_day_and_the_download_stops(): void
    {
        $this->api('POST', '/me/data-export')->assertStatus(202);
        $path = (string) DB::table('jobseeker_data_exports')->value('path');
        Storage::assertExists($path);

        // A ready copy past its day is already refused, before the cleanup runs.
        Carbon::setTestNow('2026-10-06 09:00:01');
        $this->api('GET', '/me/data-export/download')->assertNotFound();
        $this->api('GET', '/me/data-export')->assertJsonPath('data.status', 'expired')->assertJsonPath('data.download_url', null);

        $this->assertSame(0, Artisan::call('job-seeker:clean-up-data-exports'));
        $this->assertSame('Data exports closed: 1', trim(Artisan::output()));
        Storage::assertMissing($path);
        $this->assertSame('expired', DB::table('jobseeker_data_exports')->value('status'));
        $this->assertNull(DB::table('jobseeker_data_exports')->value('path'));
    }

    public function test_the_cleanup_gives_up_on_a_build_that_never_finished(): void
    {
        DB::table('jobseeker_data_exports')->insert(['user_id' => $this->userId, 'status' => 'building', 'created_at' => '2026-10-05 07:00:00', 'updated_at' => '2026-10-05 07:00:00']);

        Artisan::call('job-seeker:clean-up-data-exports');

        $this->assertSame('failed', DB::table('jobseeker_data_exports')->value('status'));
        $this->api('POST', '/me/data-export')->assertStatus(202);
    }

    public function test_erasing_the_account_deletes_the_archives_and_the_requests(): void
    {
        $this->api('POST', '/me/data-export')->assertStatus(202);
        $path = (string) DB::table('jobseeker_data_exports')->value('path');

        $this->app->make(EraseJobSeekerData::class)->execute($this->userId);

        Storage::assertMissing($path);
        $this->assertSame(0, DB::table('jobseeker_data_exports')->count());
    }
}
