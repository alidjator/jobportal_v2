<?php

namespace Tests\Feature\JobSeeker;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\JobSeeker\Application\JobSeekerSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Tests\FeatureTestCase;

final class ResumeTest extends FeatureTestCase
{
    private int $userId;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        Storage::fake();
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
        return (int) UserModel::query()->create(['type' => $type, 'name' => 'Siti', 'email' => $email, 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00'])->id;
    }

    private function tokenFor(int $userId): string
    {
        return UserModel::query()->findOrFail($userId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1'.$uri, $body);
    }

    private function pdf(string $extra = ''): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n{$extra}\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    private function upload(?string $contents = null, string $clientName = 'Siti CV.pdf', ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->post('/api/v1/me/resumes', ['file' => UploadedFile::fake()->createWithContent($clientName, $contents ?? $this->pdf())], ['Accept' => 'application/json']);
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        return Storage::allFiles('jobseeker/resumes');
    }

    // ----- access -----

    public function test_every_resume_endpoint_needs_a_signed_in_job_seeker(): void
    {
        foreach ([['GET', '/me/resumes'], ['POST', '/me/resumes'], ['PATCH', '/me/resumes/1'], ['DELETE', '/me/resumes/1'], ['GET', '/me/resumes/1/download']] as [$method, $uri]) {
            $this->json($method, '/api/v1'.$uri)->assertStatus(401);
        }

        $member = $this->tokenFor($this->account('company_member', 'member@example.test'));
        $this->upload(token: $member)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
        $this->api('GET', '/me/resumes', token: $member)->assertStatus(403);
        $this->assertSame([], $this->storedFiles());
    }

    // ----- upload -----

    public function test_a_pdf_is_stored_under_a_random_name_and_listed(): void
    {
        $response = $this->upload()->assertCreated();

        $response->assertJsonPath('data.name', 'Siti CV.pdf')->assertJsonPath('data.size_bytes', strlen($this->pdf()));
        $this->assertNotNull($response->json('data.created_at'));
        $this->assertArrayNotHasKey('path', $response->json('data'));

        $files = $this->storedFiles();
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('#^jobseeker/resumes/[0-9a-f]{32}\.pdf$#', $files[0]);
        $this->assertSame($this->pdf(), Storage::get($files[0]));
        $this->assertSame($this->userId, (int) DB::table('jobseeker_resumes')->value('user_id'));

        $this->api('GET', '/me/resumes')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $response->json('data.id'));
        $this->api('GET', '/me/profile')->assertJsonPath('data.resumes.0.name', 'Siti CV.pdf');
    }

    public function test_the_client_file_name_is_cleaned_and_shortened(): void
    {
        $this->assertSame('cv.pdf', $this->upload(clientName: '../../etc/cv.pdf')->assertCreated()->json('data.name'));
        $this->assertSame(100, mb_strlen($this->upload(clientName: str_repeat('a', 150).'.pdf')->assertCreated()->json('data.name')));
        $this->assertSame('cv.pdf', $this->upload(clientName: "c\u{202E}v.pdf")->assertCreated()->json('data.name'));
    }

    public function test_the_file_is_checked_by_its_content_not_by_its_name(): void
    {
        $this->upload('just some text', 'cv.pdf')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-resume');
        $this->upload("\x89PNG\r\n\x1a\n".str_repeat('x', 100), 'cv.pdf')->assertStatus(422);
        $this->upload('  %PDF-1.4 starts late', 'cv.pdf')->assertStatus(422);
        $this->upload($this->pdf(), 'cv.txt')->assertCreated();
        $this->assertCount(1, $this->storedFiles());
    }

    public function test_a_pdf_with_active_content_or_encryption_is_refused(): void
    {
        foreach (['/Encrypt 5 0 R', '/JavaScript (app.alert(1))', '/S /JavaScript', '/JS (app.alert(1))', '/Launch << /F (cmd.exe) >>', '/EmbeddedFile', '/Type /EmbeddedFile'] as $extra) {
            $this->upload($this->pdf($extra))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-resume');
        }

        $this->assertSame([], $this->storedFiles());
        $this->assertSame(0, DB::table('jobseeker_resumes')->count());
    }

    public function test_names_that_only_look_like_the_forbidden_ones_are_accepted(): void
    {
        $this->upload($this->pdf('/JSON /Encryption /JavaScripts /Launcher'))->assertCreated();
    }

    public function test_the_size_limit_follows_the_admin_setting(): void
    {
        $this->upload($this->pdf(str_repeat('a', 5120 * 1024)))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:file-too-large')->assertJsonPath('max_size_kb', 5120);
        $this->assertSame([], $this->storedFiles());

        $this->app->make(SettingsStore::class)->setGlobal(JobSeekerSettings::RESUME_MAX_SIZE_KB, 512, null);
        $this->upload($this->pdf(str_repeat('a', 600 * 1024)))->assertStatus(422)->assertJsonPath('max_size_kb', 512);
        $this->upload($this->pdf(str_repeat('a', 500 * 1024)))->assertCreated();
    }

    public function test_the_resume_size_setting_has_its_default_and_range(): void
    {
        $catalog = $this->app->make(SettingsCatalog::class)->find(JobSeekerSettings::RESUME_MAX_SIZE_KB);

        $this->assertSame(5120, $catalog->default);
        $this->assertSame(512, $catalog->normalize(512));
        $this->assertSame(10240, $catalog->normalize(10240));
        $this->assertFalse($catalog->overridable);

        foreach ([511, 10241] as $bad) {
            try {
                $catalog->normalize($bad);
                $this->fail("{$bad} was accepted");
            } catch (BusinessRuleViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_missing_file_is_a_validation_error(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->postJson('/api/v1/me/resumes', [])->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_at_most_five_resumes_and_a_refused_upload_leaves_no_file(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->upload($this->pdf("% {$i}"), "cv-{$i}.pdf")->assertCreated();
        }

        $this->upload($this->pdf('% six'), 'cv-6.pdf')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:resume-limit-reached');
        $this->assertCount(5, $this->storedFiles());
        $this->assertSame(5, DB::table('jobseeker_resumes')->count());

        $first = (int) DB::table('jobseeker_resumes')->orderBy('id')->value('id');
        $this->api('DELETE', "/me/resumes/{$first}")->assertNoContent();
        $this->upload($this->pdf('% six'), 'cv-6.pdf')->assertCreated();
        $this->assertCount(5, $this->storedFiles());
    }

    public function test_the_newest_resume_is_listed_first(): void
    {
        $first = (int) $this->upload($this->pdf('% a'), 'first.pdf')->json('data.id');
        Carbon::setTestNow('2026-10-05 10:00:00');
        $second = (int) $this->upload($this->pdf('% b'), 'second.pdf')->json('data.id');

        $this->assertSame([$second, $first], array_column($this->api('GET', '/me/resumes')->json('data'), 'id'));
    }

    // ----- rename, delete, download -----

    public function test_a_resume_is_renamed(): void
    {
        $id = (int) $this->upload()->json('data.id');

        $this->api('PATCH', "/me/resumes/{$id}", ['name' => '  Backend CV 2026  '])->assertOk()->assertJsonPath('data.name', 'Backend CV 2026');
        $this->assertSame('Backend CV 2026', DB::table('jobseeker_resumes')->where('id', $id)->value('name'));

        foreach ([str_repeat('a', 101), '../secret', "tab\there", '   '] as $bad) {
            $this->api('PATCH', "/me/resumes/{$id}", ['name' => $bad])->assertStatus(422);
        }

        $this->api('PATCH', "/me/resumes/{$id}", [])->assertStatus(422);
        $this->api('PATCH', "/me/resumes/{$id}", ['name' => str_repeat('a', 100)])->assertOk();
    }

    public function test_deleting_a_resume_removes_the_row_and_the_file(): void
    {
        $id = (int) $this->upload()->json('data.id');
        $this->assertCount(1, $this->storedFiles());

        $this->api('DELETE', "/me/resumes/{$id}")->assertNoContent();

        $this->assertSame([], $this->storedFiles());
        $this->assertSame(0, DB::table('jobseeker_resumes')->count());
        $this->api('DELETE', "/me/resumes/{$id}")->assertStatus(404);
        $this->api('GET', '/me/profile')->assertJsonPath('data.completeness.missing', ['phone', 'location', 'history', 'resume']);
    }

    public function test_the_owner_downloads_the_file_with_safe_headers(): void
    {
        $id = (int) $this->upload($this->pdf('% mine'), 'Siti CV.pdf')->json('data.id');

        $response = $this->api('GET', "/me/resumes/{$id}/download")->assertOk();

        $this->assertSame($this->pdf('% mine'), $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Siti CV.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_a_name_with_other_letters_still_downloads(): void
    {
        $id = (int) $this->upload(clientName: 'Résumé Zoë 履歴書.pdf')->json('data.id');

        $response = $this->api('GET', "/me/resumes/{$id}/download")->assertOk();

        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('filename*=utf-8', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_the_download_adds_pdf_only_when_the_name_has_none(): void
    {
        $id = (int) $this->upload()->json('data.id');
        $this->api('PATCH', "/me/resumes/{$id}", ['name' => 'Backend CV'])->assertOk();

        $this->assertStringContainsString('Backend CV.pdf', (string) $this->api('GET', "/me/resumes/{$id}/download")->headers->get('Content-Disposition'));
    }

    public function test_a_resume_of_another_account_does_not_exist(): void
    {
        $other = $this->tokenFor($this->account('job_seeker', 'budi@example.test'));
        $foreign = (int) $this->upload(token: $other)->json('data.id');

        $this->api('GET', "/me/resumes/{$foreign}/download")->assertStatus(404);
        $this->api('PATCH', "/me/resumes/{$foreign}", ['name' => 'Hijacked'])->assertStatus(404);
        $this->api('DELETE', "/me/resumes/{$foreign}")->assertStatus(404);
        $this->api('GET', '/me/resumes')->assertExactJson(['data' => []]);
        $this->assertCount(1, $this->storedFiles());
    }

    public function test_a_resume_whose_file_is_missing_is_not_found(): void
    {
        $id = (int) $this->upload()->json('data.id');
        Storage::delete($this->storedFiles()[0]);

        $this->api('GET', "/me/resumes/{$id}/download")->assertStatus(404);
        $this->api('DELETE', "/me/resumes/{$id}")->assertNoContent();
    }
}
