<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Src\Identity\Application\IdentitySettings;
use Src\LegacyImport\Application\ImportReport;
use Src\LegacyImport\Application\ImportRunner;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\CreatesReferenceRows;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class ImagesImporterTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->setUpLegacyDatabase();

        $this->root = sys_get_temp_dir().'/legacy-images-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/public/uploads', 0777, true);
        config(['legacy_import.files_root' => $this->root]);

        $this->industryId();
        $this->organizationTypeId();
        $this->legacyMasterItem('industry_types', 1, ['en' => 'Technology']);
        $this->legacyMasterItem('organization_types', 1, ['en' => 'Private Company']);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));

        parent::tearDown();
    }

    /** A PNG of the given size, as bytes. A noisy one is large on purpose. */
    private function png(int $width, int $height, bool $noisy = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 120, 200));

        if ($noisy) {
            for ($i = 0; $i < 20000; $i++) {
                imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), (int) imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            }
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function legacyFile(string $name, string $contents): string
    {
        file_put_contents($this->root.'/public/uploads/'.$name, $contents);

        return 'uploads/'.$name;
    }

    private function runAll(bool $write = true): ImportReport
    {
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts', 'companies'] as $entity) {
            $runner->run($entity, true);
        }

        return $runner->run('images', $write)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report, string $name): array
    {
        return $report->toArray(true)['entities'][$name];
    }

    /** @return array{int, int} the width and height of a stored image */
    private function dimensions(string $path): array
    {
        $size = getimagesizefromstring((string) Storage::get($path));

        return [$size[0], $size[1]];
    }

    // ----- avatars -----

    public function test_a_profile_photo_is_processed_and_stored_under_a_random_name(): void
    {
        $this->legacyUser(['email' => 'a@example.test'], ['photo' => $this->legacyFile('photo.png', $this->png(800, 600))]);

        $report = $this->runAll();

        $path = (string) DB::table('identity_users')->value('avatar_path');
        $this->assertMatchesRegularExpression('#^identity/avatars/[0-9a-f]{32}\.(jpg|png)$#', $path);
        Storage::assertExists($path);
        $this->assertSame([512, 384], $this->dimensions($path));
        $this->assertSame(['read' => 1, 'imported' => 1, 'skipped' => 0], array_intersect_key($this->entity($report, 'images.avatar'), array_flip(['read', 'imported', 'skipped'])));
        $this->assertSame(1, DB::table('legacyimport_id_map')->where('entity', 'images.avatar')->count());
    }

    public function test_the_candidate_photo_wins_over_the_account_image_and_the_default_picture_is_nothing(): void
    {
        $this->legacyUser(['email' => 'a@example.test', 'image' => $this->legacyFile('account.png', $this->png(100, 100))], ['photo' => $this->legacyFile('photo.png', $this->png(200, 200))]);
        $this->legacyUser(['email' => 'b@example.test', 'image' => $this->legacyFile('only-account.png', $this->png(300, 300))]);
        $this->legacyUser(['email' => 'c@example.test', 'image' => 'backend/image/default.png'], ['photo' => 'uploads/default.png']);
        $this->legacyUser(['email' => 'd@example.test'], ['photo' => '']);

        $report = $this->runAll();

        $sizes = DB::table('identity_users')->orderBy('id')->pluck('avatar_path', 'email')->map(fn ($path): ?array => $path === null ? null : $this->dimensions($path))->all();
        $this->assertSame(['a@example.test' => [200, 200], 'b@example.test' => [300, 300], 'c@example.test' => null, 'd@example.test' => null], $sizes);
        $this->assertSame(2, $this->entity($report, 'images.avatar')['read']);
    }

    public function test_a_missing_broken_or_too_large_image_is_skipped_with_its_reason(): void
    {
        $missing = $this->legacyUser(['email' => 'a@example.test'], ['photo' => 'uploads/gone.png']);
        $broken = $this->legacyUser(['email' => 'b@example.test'], ['photo' => $this->legacyFile('broken.png', 'this is not an image')]);
        $large = $this->legacyUser(['email' => 'c@example.test'], ['photo' => $this->legacyFile('large.png', $this->png(600, 600, true))]);
        $traversal = $this->legacyUser(['email' => 'd@example.test'], ['photo' => '../../../etc/passwd']);
        $this->app->make(SettingsStore::class)->setGlobal(IdentitySettings::AVATAR_MAX_SIZE_KB, 100, null);

        $report = $this->entity($this->runAll(), 'images.avatar');

        $this->assertSame([$missing => 'file-missing', $broken => 'invalid-image', $large => 'too-large', $traversal => 'file-missing'], collect($report['skipped_rows'])->pluck('reason', 'legacy_id')->all());
        $this->assertSame(0, DB::table('identity_users')->whereNotNull('avatar_path')->count());
        $this->assertSame([], Storage::allFiles('identity'));
    }

    public function test_without_a_legacy_folder_every_image_is_skipped_and_nothing_breaks(): void
    {
        $this->legacyUser(['email' => 'a@example.test'], ['photo' => $this->legacyFile('photo.png', $this->png(50, 50))]);
        config(['legacy_import.files_root' => '']);

        $report = $this->entity($this->runAll(), 'images.avatar');

        $this->assertSame([['legacy_id' => 1, 'reason' => 'files-root-not-configured']], $report['skipped_rows']);
    }

    public function test_an_account_that_already_has_an_avatar_keeps_it(): void
    {
        $this->legacyUser(['email' => 'a@example.test'], ['photo' => $this->legacyFile('photo.png', $this->png(100, 100))]);
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts'] as $entity) {
            $runner->run($entity, true);
        }

        DB::table('identity_users')->update(['avatar_path' => 'identity/avatars/chosen-by-the-user.png']);
        $runner->run('companies', true);
        $report = $this->entity($runner->run('images', true)['report'], 'images.avatar');

        $this->assertSame('identity/avatars/chosen-by-the-user.png', DB::table('identity_users')->value('avatar_path'));
        $this->assertSame(1, $report['notes']['already-has-image']);
        $this->assertSame([], Storage::allFiles('identity/avatars'));
    }

    // ----- logos and banners -----

    public function test_a_company_logo_and_banner_follow_the_new_size_rules(): void
    {
        $owner = $this->legacyUser(['role' => 'company', 'email' => 'owner@example.test']);
        $this->legacyCompany($owner, 1, 1, ['logo' => $this->legacyFile('logo.png', $this->png(1000, 1000)), 'banner' => $this->legacyFile('banner.png', $this->png(2400, 1200))]);

        $report = $this->runAll();

        $company = DB::table('company_companies')->first();
        $this->assertMatchesRegularExpression('#^company/logos/[0-9a-f]{32}\.(jpg|png)$#', $company->logo_path);
        $this->assertMatchesRegularExpression('#^company/banners/[0-9a-f]{32}\.(jpg|png)$#', $company->banner_path);
        $this->assertSame([512, 512], $this->dimensions($company->logo_path));
        $this->assertSame([1200, 600], $this->dimensions($company->banner_path));
        $this->assertSame(1, $this->entity($report, 'images.logo')['imported']);
        $this->assertSame(1, $this->entity($report, 'images.banner')['imported']);
    }

    // ----- safe to repeat, and a dry run -----

    public function test_running_it_again_stores_nothing_twice(): void
    {
        $this->legacyUser(['email' => 'a@example.test'], ['photo' => $this->legacyFile('photo.png', $this->png(100, 100))]);
        $this->runAll();
        $first = DB::table('identity_users')->value('avatar_path');

        $report = $this->app->make(ImportRunner::class)->run('images', true)['report'];

        $this->assertSame($first, DB::table('identity_users')->value('avatar_path'));
        $this->assertCount(1, Storage::allFiles('identity/avatars'));
        $this->assertArrayNotHasKey('images.avatar', $report->toArray(true)['entities']);
    }

    public function test_a_dry_run_reads_and_checks_the_images_but_stores_nothing(): void
    {
        $this->legacyUser(['email' => 'a@example.test'], ['photo' => $this->legacyFile('photo.png', $this->png(100, 100))]);
        $this->legacyUser(['email' => 'b@example.test'], ['photo' => $this->legacyFile('broken.png', 'nope')]);

        $report = $this->entity($this->runAll(false), 'images.avatar');

        $this->assertSame(['read' => 2, 'imported' => 1, 'skipped' => 1], array_intersect_key($report, array_flip(['read', 'imported', 'skipped'])));
        $this->assertSame(0, DB::table('identity_users')->whereNotNull('avatar_path')->count());
        $this->assertSame([], Storage::allFiles('identity'));
        $this->assertSame(0, DB::table('legacyimport_id_map')->where('entity', 'images.avatar')->count());
    }
}
