<?php

namespace Tests\Feature\Company;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Shared\Application\Settings\SettingsCatalog;
use Tests\FeatureTestCase;

final class CompanyImageTest extends FeatureTestCase
{
    private int $companyId;

    private string $adminToken;

    private string $recruiterToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        Storage::fake();

        $industry = $this->master('reference_industries', 'technology');
        $type = $this->master('reference_organization_types', 'private-company');
        $this->companyId = (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Maju Bersama', 'slug' => 'pt-maju-bersama', 'status' => 'active', 'industry_id' => $industry, 'organization_type_id' => $type,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->adminToken = $this->token($this->member('Andi Admin', 'andi@example.test', 'company_admin'));
        $this->recruiterToken = $this->token($this->member('Rina Rekruter', 'rina@example.test', 'recruiter'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function master(string $table, string $slug): int
    {
        return (int) DB::table($table)->insertGetId([
            'name' => json_encode(['id' => $slug, 'en' => $slug]), 'slug' => $slug, 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(string $name, string $email, string $role): UserModel
    {
        $user = UserModel::query()->create(['type' => 'company_member', 'name' => $name, 'email' => $email, 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00']);
        DB::table('company_members')->insert(['company_id' => $this->companyId, 'user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function token(UserModel $user): string
    {
        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    private function imageBytes(string $type = 'png', int $width = 100, int $height = 50, bool $transparent = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 30, 90, 200, $transparent ? 64 : 0));

        ob_start();
        match ($type) {
            'jpeg' => imagejpeg($image, null, 90),
            'webp' => imagewebp($image, null, 90),
            default => imagepng($image),
        };

        return (string) ob_get_clean();
    }

    private function upload(string $kind, string $contents, string $clientName = 'photo.png', ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->post(
            "/api/v1/company/{$kind}",
            ['file' => UploadedFile::fake()->createWithContent($clientName, $contents)],
            ['Accept' => 'application/json'],
        );
    }

    private function api(string $method, string $uri, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->json($method, $uri);
    }

    private function row(): object
    {
        return DB::table('company_companies')->where('id', $this->companyId)->first();
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        return Storage::allFiles();
    }

    // ----- access -----

    public function test_the_image_routes_need_a_signed_in_user_except_the_public_ones(): void
    {
        foreach (['logo', 'banner'] as $kind) {
            $this->postJson("/api/v1/company/{$kind}")->assertStatus(401);
            $this->deleteJson("/api/v1/company/{$kind}")->assertStatus(401);
            $this->getJson("/api/v1/company/{$kind}")->assertStatus(401);
            $this->getJson("/api/v1/companies/pt-maju-bersama/{$kind}")->assertStatus(404);
        }
    }

    public function test_a_recruiter_a_job_seeker_and_a_suspended_company_cannot_change_the_pictures(): void
    {
        $seeker = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'en']);

        $this->upload('logo', $this->imageBytes(), token: $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-admin-required');
        $this->api('DELETE', '/api/v1/company/logo', $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-admin-required');
        $this->upload('logo', $this->imageBytes(), token: $this->token($seeker))->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');

        DB::table('company_companies')->update(['status' => 'suspended']);
        $this->upload('logo', $this->imageBytes())->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');

        $this->assertSame([], $this->storedFiles());
        $this->assertNull($this->row()->logo_path);
    }

    // ----- uploading -----

    public function test_a_logo_is_stored_under_a_random_name_and_served_back(): void
    {
        $this->upload('logo', $this->imageBytes('png', 100, 50), 'My Company Logo.PNG')->assertNoContent();

        $path = (string) $this->row()->logo_path;
        $this->assertMatchesRegularExpression('#^company/logos/[0-9a-f]{32}\.jpg$#', $path);
        $this->assertStringNotContainsString('Company', $path);
        Storage::assertExists($path);
        $this->assertSame([$path], $this->storedFiles());
        $this->assertSame([100, 50], array_slice(getimagesizefromstring((string) Storage::get($path)), 0, 2));

        $own = $this->api('GET', '/api/v1/company/logo')->assertOk();
        $this->assertSame('image/jpeg', $own->headers->get('Content-Type'));
        $this->assertSame(Storage::get($path), $own->getContent());
        $this->assertSame('nosniff', $own->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('private', (string) $own->headers->get('Cache-Control'));

        $this->app['auth']->forgetGuards();
        $public = $this->get('/api/v1/companies/pt-maju-bersama/logo')->assertOk();
        $this->assertSame(Storage::get($path), $public->getContent());
        $this->assertSame('inline', $public->headers->get('Content-Disposition'));
        $this->assertSame("default-src 'none'", $public->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('public', (string) $public->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', (string) $public->headers->get('Cache-Control'));
        $this->assertNotNull($public->headers->get('ETag'));
    }

    public function test_the_client_file_name_and_type_do_not_matter(): void
    {
        $this->upload('logo', $this->imageBytes('png'), '../../public/evil.php')->assertNoContent();

        $this->assertMatchesRegularExpression('#^company/logos/[0-9a-f]{32}\.jpg$#', (string) $this->row()->logo_path);
        $this->assertSame(['company', 'company/logos'], Storage::allDirectories());
        $this->assertCount(1, $this->storedFiles());
    }

    public function test_the_stored_picture_is_shrunk_and_can_keep_its_transparency(): void
    {
        $this->upload('logo', $this->imageBytes('png', 2000, 1000, true))->assertNoContent();

        $path = (string) $this->row()->logo_path;
        $this->assertStringEndsWith('.png', $path);
        $this->assertSame([512, 256], array_slice(getimagesizefromstring((string) Storage::get($path)), 0, 2));
        $this->assertSame('image/png', $this->api('GET', '/api/v1/company/logo')->headers->get('Content-Type'));
    }

    public function test_a_banner_has_its_own_size_limit_and_folder(): void
    {
        $this->upload('banner', $this->imageBytes('jpeg', 3000, 1200))->assertNoContent();

        $path = (string) $this->row()->banner_path;
        $this->assertMatchesRegularExpression('#^company/banners/[0-9a-f]{32}\.jpg$#', $path);
        $this->assertSame([1500, 600], array_slice(getimagesizefromstring((string) Storage::get($path)), 0, 2));
        $this->assertNull($this->row()->logo_path);
    }

    public function test_every_allowed_type_is_accepted(): void
    {
        foreach (['png', 'jpeg', 'webp'] as $type) {
            $this->upload('logo', $this->imageBytes($type))->assertNoContent();
        }

        $this->assertCount(1, $this->storedFiles());
    }

    public function test_replacing_a_picture_stores_the_new_one_and_deletes_the_old(): void
    {
        $this->upload('logo', $this->imageBytes('png', 100, 50))->assertNoContent();
        $first = (string) $this->row()->logo_path;
        $firstVersion = pathinfo($first, PATHINFO_FILENAME);

        $this->upload('logo', $this->imageBytes('jpeg', 80, 80))->assertNoContent();
        $second = (string) $this->row()->logo_path;

        $this->assertNotSame($first, $second);
        Storage::assertMissing($first);
        Storage::assertExists($second);
        $this->assertSame([$second], $this->storedFiles());

        $url = $this->api('GET', '/api/v1/company/profile')->json('data.logo_url');
        $this->assertStringContainsString('?v='.pathinfo($second, PATHINFO_FILENAME), (string) $url);
        $this->assertStringNotContainsString($firstVersion, (string) $url);
    }

    public function test_the_logo_and_the_banner_are_independent(): void
    {
        $this->upload('logo', $this->imageBytes())->assertNoContent();
        $this->upload('banner', $this->imageBytes())->assertNoContent();
        $logo = (string) $this->row()->logo_path;

        $this->api('DELETE', '/api/v1/company/banner')->assertNoContent();

        $this->assertSame($logo, $this->row()->logo_path);
        $this->assertNull($this->row()->banner_path);
        $this->assertSame([$logo], $this->storedFiles());
    }

    // ----- refused uploads -----

    public function test_a_file_that_is_not_an_image_is_refused_and_nothing_changes(): void
    {
        $this->upload('logo', $this->imageBytes())->assertNoContent();
        $before = (string) $this->row()->logo_path;

        $this->upload('logo', '<?php echo "hi"; ?>', 'logo.png')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-image');
        $this->upload('logo', 'GIF89a<?php system("id"); ?>', 'logo.gif')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-image');
        $this->upload('logo', '<svg xmlns="http://www.w3.org/2000/svg"/>', 'logo.svg')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-image');
        $this->upload('logo', substr($this->imageBytes('png', 300, 300), 0, 80), 'cut.png')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-image');

        $this->assertSame($before, $this->row()->logo_path);
        $this->assertSame([$before], $this->storedFiles());
    }

    public function test_a_request_without_a_file_is_refused(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withToken($this->adminToken)->postJson('/api/v1/company/logo', [])->assertStatus(422)->assertJsonValidationErrors('file', 'errors');
        $this->withToken($this->adminToken)->postJson('/api/v1/company/logo', ['file' => 'not a file'])->assertStatus(422)->assertJsonValidationErrors('file', 'errors');
    }

    public function test_a_file_over_the_size_setting_is_refused_with_the_limit_in_the_answer(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('company.logo_max_size_kb', 100, null);
        $bytes = $this->imageBytes('png', 400, 400);
        // Pad the content past 100 KB: the size is checked on the upload, before the image is opened.
        $padded = $bytes.str_repeat("\0", 101 * 1024);

        $this->upload('logo', $padded)->assertStatus(422)
            ->assertJsonPath('type', 'urn:jobportal:error:file-too-large')
            ->assertJsonPath('max_size_kb', 100);

        $this->assertSame([], $this->storedFiles());

        // The banner has its own setting and still allows this file.
        $this->upload('banner', $this->imageBytes())->assertNoContent();
    }

    public function test_a_file_exactly_at_the_size_limit_is_accepted(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('company.logo_max_size_kb', 100, null);
        $bytes = $this->imageBytes('png', 50, 50);
        // Trailing bytes after a valid PNG are ignored by the decoder and dropped by the re-encoding.
        $exact = $bytes.str_repeat("\0", 100 * 1024 - strlen($bytes));

        $this->upload('logo', $exact)->assertNoContent();
        $this->upload('logo', $exact."\0")->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:file-too-large');
    }

    public function test_the_default_size_settings_are_registered_for_admins(): void
    {
        $definitions = [];
        foreach ($this->app->make(SettingsCatalog::class)->all() as $definition) {
            $definitions[$definition->key] = $definition;
        }

        $this->assertSame(2048, $definitions['company.logo_max_size_kb']->default);
        $this->assertSame(5120, $definitions['company.banner_max_size_kb']->default);
        $this->assertSame([100, 10240], [$definitions['company.logo_max_size_kb']->min, $definitions['company.logo_max_size_kb']->max]);
    }

    // ----- removing -----

    public function test_removing_a_picture_deletes_the_file_and_is_idempotent(): void
    {
        $this->upload('logo', $this->imageBytes())->assertNoContent();

        $this->api('DELETE', '/api/v1/company/logo')->assertNoContent();

        $this->assertNull($this->row()->logo_path);
        $this->assertSame([], $this->storedFiles());
        $this->api('DELETE', '/api/v1/company/logo')->assertNoContent();
        $this->api('GET', '/api/v1/company/logo')->assertNotFound()->assertJsonPath('type', 'urn:jobportal:error:image-not-found');
    }

    // ----- reading -----

    public function test_a_company_without_pictures_has_null_urls_and_404_images(): void
    {
        $this->api('GET', '/api/v1/company/profile')->assertJsonPath('data.logo_url', null)->assertJsonPath('data.banner_url', null);
        $this->get('/api/v1/companies/pt-maju-bersama')->assertOk()->assertJsonPath('data.logo_url', null)->assertJsonPath('data.banner_url', null);
        $this->get('/api/v1/companies/pt-maju-bersama/logo')->assertNotFound()->assertJsonPath('type', 'urn:jobportal:error:image-not-found');
        $this->get('/api/v1/companies/pt-maju-bersama/banner')->assertNotFound();
    }

    public function test_the_profiles_carry_urls_with_a_version(): void
    {
        $this->upload('logo', $this->imageBytes())->assertNoContent();
        $this->upload('banner', $this->imageBytes())->assertNoContent();
        $logoVersion = pathinfo((string) $this->row()->logo_path, PATHINFO_FILENAME);
        $bannerVersion = pathinfo((string) $this->row()->banner_path, PATHINFO_FILENAME);

        $own = $this->api('GET', '/api/v1/company/profile');
        $own->assertJsonPath('data.logo_url', url('/api/v1/company/logo').'?v='.$logoVersion);
        $own->assertJsonPath('data.banner_url', url('/api/v1/company/banner').'?v='.$bannerVersion);

        $public = $this->get('/api/v1/companies/pt-maju-bersama')->assertOk();
        $public->assertJsonPath('data.logo_url', url('/api/v1/companies/pt-maju-bersama/logo').'?v='.$logoVersion);
        $public->assertJsonPath('data.banner_url', url('/api/v1/companies/pt-maju-bersama/banner').'?v='.$bannerVersion);
    }

    public function test_a_hidden_or_suspended_company_shows_no_picture_to_the_public_but_still_to_its_members(): void
    {
        $this->upload('logo', $this->imageBytes())->assertNoContent();

        DB::table('company_companies')->update(['is_visible' => false]);
        $this->get('/api/v1/companies/pt-maju-bersama/logo')->assertNotFound();
        $this->api('GET', '/api/v1/company/logo', $this->recruiterToken)->assertOk();

        DB::table('company_companies')->update(['is_visible' => true, 'status' => 'suspended']);
        $this->get('/api/v1/companies/pt-maju-bersama/logo')->assertNotFound();
        $this->api('GET', '/api/v1/company/logo')->assertOk();

        $this->get('/api/v1/companies/no-such-company/logo')->assertNotFound();
    }

    public function test_a_missing_file_is_a_plain_not_found(): void
    {
        $this->upload('logo', $this->imageBytes())->assertNoContent();
        Storage::delete((string) $this->row()->logo_path);

        $this->get('/api/v1/companies/pt-maju-bersama/logo')->assertNotFound()->assertJsonPath('type', 'urn:jobportal:error:image-not-found');
        $this->api('GET', '/api/v1/company/logo')->assertNotFound();
    }

    public function test_the_public_picture_can_be_revalidated(): void
    {
        $this->upload('logo', $this->imageBytes())->assertNoContent();

        $etag = (string) $this->get('/api/v1/companies/pt-maju-bersama/logo')->assertOk()->headers->get('ETag');

        $this->withHeaders(['If-None-Match' => $etag])->get('/api/v1/companies/pt-maju-bersama/logo')->assertStatus(304);
    }

    public function test_the_pictures_are_not_audited(): void
    {
        $this->upload('logo', $this->imageBytes())->assertNoContent();
        $this->api('DELETE', '/api/v1/company/logo')->assertNoContent();

        $this->assertSame(0, DB::table('platform_audit_logs')->count());
    }
}
