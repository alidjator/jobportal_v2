<?php

namespace Tests\Feature\Identity;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Identity\Application\IdentitySettings;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Tests\FeatureTestCase;

final class AvatarTest extends FeatureTestCase
{
    private int $userId;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->userId = $this->account('job_seeker', 'siti@example.test');
        $this->token = $this->tokenFor($this->userId);
    }

    private function account(string $type, string $email): int
    {
        return (int) UserModel::query()->create(['type' => $type, 'name' => 'Siti', 'email' => $email, 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00'])->id;
    }

    private function tokenFor(int $userId): string
    {
        return UserModel::query()->findOrFail($userId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    private function imageBytes(string $type = 'png', int $width = 100, int $height = 50): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 90, 200));

        ob_start();
        match ($type) {
            'jpeg' => imagejpeg($image, null, 90),
            'webp' => imagewebp($image, null, 90),
            default => imagepng($image),
        };

        return (string) ob_get_clean();
    }

    private function upload(?string $contents = null, ?string $token = null, string $clientName = 'me.png'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->post('/api/v1/me/avatar', ['file' => UploadedFile::fake()->createWithContent($clientName, $contents ?? $this->imageBytes())], ['Accept' => 'application/json']);
    }

    private function show(?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->getJson('/api/v1/me/avatar');
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        return Storage::allFiles('identity/avatars');
    }

    private function avatarPath(?int $userId = null): ?string
    {
        return DB::table('identity_users')->where('id', $userId ?? $this->userId)->value('avatar_path');
    }

    public function test_the_avatar_endpoints_need_a_signed_in_public_account(): void
    {
        $this->getJson('/api/v1/me/avatar')->assertStatus(401);
        $this->postJson('/api/v1/me/avatar')->assertStatus(401);
        $this->deleteJson('/api/v1/me/avatar')->assertStatus(401);
    }

    public function test_a_job_seeker_and_a_company_member_both_have_a_picture(): void
    {
        $member = $this->account('company_member', 'member@example.test');

        $this->upload()->assertNoContent();
        $this->upload(token: $this->tokenFor($member))->assertNoContent();

        $this->assertNotNull($this->avatarPath());
        $this->assertNotNull($this->avatarPath($member));
        $this->assertNotSame($this->avatarPath(), $this->avatarPath($member));
        $this->assertCount(2, $this->storedFiles());
    }

    public function test_the_picture_is_stored_under_a_random_name_and_served_back_to_its_owner(): void
    {
        $this->show()->assertStatus(404)->assertJsonPath('type', 'urn:onesis:error:avatar-not-found');

        $this->upload()->assertNoContent();

        $files = $this->storedFiles();
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('#^identity/avatars/[0-9a-f]{32}\.jpg$#', $files[0]);
        $this->assertSame($files[0], $this->avatarPath());

        $response = $this->show()->assertOk();
        // An opaque picture is stored as a JPEG; only a picture with transparency stays a PNG.
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame('inline', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame(Storage::get($files[0]), $response->getContent());
    }

    public function test_the_account_says_whether_it_has_a_picture(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.has_avatar', false);

        $this->upload()->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.has_avatar', true);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->deleteJson('/api/v1/me/avatar')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.has_avatar', false);
    }

    public function test_the_picture_is_shrunk_to_fit_512_without_cropping_or_enlarging(): void
    {
        $this->upload($this->imageBytes('png', 1024, 768))->assertNoContent();
        $size = getimagesizefromstring((string) Storage::get($this->storedFiles()[0]));
        $this->assertSame([512, 384], [$size[0], $size[1]]);

        $this->upload($this->imageBytes('png', 200, 100))->assertNoContent();
        $size = getimagesizefromstring((string) Storage::get($this->storedFiles()[0]));
        $this->assertSame([200, 100], [$size[0], $size[1]]);
    }

    public function test_the_client_file_name_and_type_do_not_matter_and_every_allowed_type_works(): void
    {
        $this->upload($this->imageBytes('jpeg'), clientName: 'evil.php')->assertNoContent();
        $this->upload($this->imageBytes('webp'), clientName: 'photo.gif')->assertNoContent();
        $this->upload($this->imageBytes('png'), clientName: 'photo.jpg')->assertNoContent();

        $this->assertMatchesRegularExpression('#\.(png|jpg)$#', (string) $this->avatarPath());
    }

    public function test_replacing_the_picture_stores_the_new_one_and_deletes_the_old(): void
    {
        $this->upload()->assertNoContent();
        $first = $this->avatarPath();

        $this->upload($this->imageBytes('jpeg'))->assertNoContent();

        $this->assertNotSame($first, $this->avatarPath());
        $this->assertCount(1, $this->storedFiles());
        $this->assertFalse(Storage::exists($first));
    }

    public function test_a_file_that_is_not_an_image_is_refused_and_nothing_changes(): void
    {
        $this->upload()->assertNoContent();
        $path = $this->avatarPath();

        $this->upload('plain text, not an image')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-image');
        $this->upload("%PDF-1.4\n%%EOF")->assertStatus(422);

        $this->assertSame($path, $this->avatarPath());
        $this->assertCount(1, $this->storedFiles());

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->postJson('/api/v1/me/avatar', [])->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_the_size_limit_follows_the_admin_setting(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(IdentitySettings::AVATAR_MAX_SIZE_KB, 100, null);
        $large = $this->imageBytes('png', 1, 1).str_repeat('a', 101 * 1024);

        $this->upload($large)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:file-too-large')->assertJsonPath('max_size_kb', 100);
        $this->assertSame([], $this->storedFiles());
        $this->assertNull($this->avatarPath());
    }

    public function test_the_size_setting_has_its_default_and_range(): void
    {
        $definition = $this->app->make(SettingsCatalog::class)->find(IdentitySettings::AVATAR_MAX_SIZE_KB);

        $this->assertSame(2048, $definition->default);
        $this->assertFalse($definition->overridable);
        $this->assertSame(100, $definition->normalize(100));
        $this->assertSame(10240, $definition->normalize(10240));

        foreach ([99, 10241] as $bad) {
            try {
                $definition->normalize($bad);
                $this->fail("{$bad} was accepted");
            } catch (BusinessRuleViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_removing_the_picture_deletes_the_file_and_is_idempotent(): void
    {
        $this->upload()->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->deleteJson('/api/v1/me/avatar')->assertNoContent();

        $this->assertNull($this->avatarPath());
        $this->assertSame([], $this->storedFiles());
        $this->show()->assertStatus(404);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->deleteJson('/api/v1/me/avatar')->assertNoContent();
    }

    public function test_one_account_never_reaches_the_picture_of_another(): void
    {
        $other = $this->account('job_seeker', 'budi@example.test');
        $otherToken = $this->tokenFor($other);
        $this->upload(token: $otherToken)->assertNoContent();

        $this->show()->assertStatus(404);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->deleteJson('/api/v1/me/avatar')->assertNoContent();
        $this->assertNotNull($this->avatarPath($other));
        $this->assertCount(1, $this->storedFiles());
    }

    public function test_a_missing_file_is_a_plain_not_found(): void
    {
        $this->upload()->assertNoContent();
        Storage::delete($this->storedFiles()[0]);

        $this->show()->assertStatus(404);
    }
}
