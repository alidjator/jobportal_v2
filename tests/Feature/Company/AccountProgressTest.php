<?php

namespace Tests\Feature\Company;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Shared\Application\Contracts\FileStorage;

class AccountProgressTest extends CompanyTestCase
{
    /** @var list<string> */
    private array $stored = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(FileStorage::class, new class($this->stored) implements FileStorage
        {
            public function __construct(private array &$stored) {}

            public function storeFile(UploadedFile $file, string $directory): string
            {
                return $this->stored[] = "uploads/{$directory}/".Str::random(8).'.bin';
            }

            public function storeImage(UploadedFile $file, string $directory, ?array $fit = null, int $quality = 60): string
            {
                return $this->stored[] = "{$directory}/".Str::random(8).'.png';
            }

            public function delete(?string $path): void {}
        });
    }

    public function test_requires_company_auth(): void
    {
        $this->getJson('/api/company/account-progress')->assertUnauthorized();
        $this->postJson('/api/company/account-progress', ['step' => 1])->assertUnauthorized();
    }

    public function test_fetch_returns_form_data_without_secrets(): void
    {
        $made = $this->actingAsCompany();
        DB::table('industry_type_translations')->insert(['industry_type_id' => $made['industry'], 'name' => 'Tech', 'locale' => 'en']);
        $country = DB::table('countries')->insertGetId(['name' => 'Testland '.Str::random(5), 'created_at' => now(), 'updated_at' => now()]);

        $data = $this->getJson('/api/company/account-progress')->assertOk()->json('data');

        $this->assertSame($made['user']->id, $data['user']['id']);
        $this->assertSame($made['company'], $data['user']['company']['id']);
        $this->assertArrayNotHasKey('password', $data['user']);
        $this->assertSame('Tech', collect($data['industry_types'])->firstWhere('id', $made['industry'])['name']);
        foreach (['organization_types', 'team_sizes', 'socials', 'countries'] as $key) {
            $this->assertIsArray($data[$key]);
        }
        $this->assertContains($country, array_column($data['countries'], 'id'));
    }

    public function test_step_one_company_requires_logo_banner_and_bio(): void
    {
        $this->actingAsCompany();

        $this->postJson('/api/company/account-progress', ['step' => 1])->assertUnprocessable()
            ->assertJsonValidationErrors(['image', 'banner', 'bio'])
            ->assertJsonPath('errors.image.0', 'The logo field is required.');
    }

    public function test_step_one_saves_profile_and_images(): void
    {
        $made = $this->actingAsCompany();

        $response = $this->post('/api/company/account-progress', [
            'step' => '1', 'name' => 'New Name', 'bio' => 'We build things',
            'image' => UploadedFile::fake()->image('logo.png'), 'banner' => UploadedFile::fake()->image('banner.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $response->assertJsonPath('data.id', $made['company'])->assertJsonPath('data.bio', 'We build things')->assertJsonPath('data.name', 'New Name');
        $row = DB::table('companies')->find($made['company']);
        $this->assertStringStartsWith('images/company/', $row->logo);
        $this->assertStringStartsWith('images/company/', $row->banner);
        $this->assertSame('New Name', DB::table('users')->where('id', $made['user']->id)->value('name'));
    }

    public function test_step_one_keeps_name_and_existing_images_when_omitted(): void
    {
        $made = $this->actingAsCompany(['logo' => 'images/company/old.png', 'banner' => 'images/company/oldb.png'], ['name' => 'Keep Me']);

        $this->postJson('/api/company/account-progress', ['step' => 'company', 'bio' => 'Updated bio'])->assertOk();

        $this->assertSame('Keep Me', DB::table('users')->where('id', $made['user']->id)->value('name'));
        $this->assertSame('images/company/old.png', DB::table('companies')->where('id', $made['company'])->value('logo'));
        $this->assertSame('Updated bio', DB::table('companies')->where('id', $made['company'])->value('bio'));
    }

    public function test_step_one_rejects_bad_image(): void
    {
        $this->actingAsCompany(['logo' => 'x.png', 'banner' => 'y.png']);

        $this->post('/api/company/account-progress', ['step' => 1, 'bio' => 'b', 'image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_step_two_founding_validates_and_saves(): void
    {
        $made = $this->actingAsCompany();
        $this->postJson('/api/company/account-progress', ['step' => 2, 'website' => 'not-a-url'])->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_type_id', 'industry_type_id', 'vision', 'website']);

        $org = DB::table('organization_types')->insertGetId(['created_at' => now()]);
        $industry = DB::table('industry_types')->insertGetId(['created_at' => now()]);
        $size = DB::table('team_sizes')->insertGetId(['created_at' => now()]);

        $this->postJson('/api/company/account-progress', [
            'step' => 2, 'organization_type_id' => $org, 'industry_type_id' => $industry, 'team_size_id' => $size,
            'establishment_date' => '2015-05-20', 'website' => 'https://acme.test', 'vision' => 'Be great',
        ])->assertOk()->assertJsonPath('data.success', 'Company profile updated successfully.');

        $this->assertDatabaseHas('companies', [
            'id' => $made['company'], 'organization_type_id' => $org, 'industry_type_id' => $industry,
            'team_size_id' => $size, 'establishment_date' => '2015-05-20', 'website' => 'https://acme.test', 'vision' => 'Be great',
        ]);
    }

    public function test_step_two_creates_new_types_by_name_and_rejects_unknown_ids(): void
    {
        $made = $this->actingAsCompany();
        $name = 'Quantum '.Str::random(6);

        $this->postJson('/api/company/account-progress', [
            'step' => 'founding', 'organization_type_id' => $name, 'industry_type_id' => $name, 'vision' => 'v',
        ])->assertOk();

        $industryId = (int) DB::table('industry_type_translations')->where('name', $name)->value('industry_type_id');
        $this->assertGreaterThan(0, $industryId);
        $this->assertSame($industryId, (int) DB::table('companies')->where('id', $made['company'])->value('industry_type_id'));

        $this->postJson('/api/company/account-progress', [
            'step' => 2, 'organization_type_id' => 987654321, 'industry_type_id' => $industryId, 'vision' => 'v',
        ])->assertStatus(400)->assertJsonStructure(['error']);
    }

    public function test_step_three_replaces_social_links(): void
    {
        $made = $this->actingAsCompany();
        DB::table('social_links')->insert(['user_id' => $made['user']->id, 'social_media' => 'reddit', 'url' => 'https://old', 'created_at' => now(), 'updated_at' => now()]);

        $this->postJson('/api/company/account-progress', ['step' => 3, 'social_media' => [
            ['social_media' => 'facebook', 'url' => 'https://fb.test/a'],
            ['social_media' => 'github', 'url' => ''],
        ]])->assertOk();

        $rows = DB::table('social_links')->where('user_id', $made['user']->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('facebook', $rows[0]->social_media);

        // format dua larik paralel (dokumentasi API)
        $this->postJson('/api/company/account-progress', ['step' => 3, 'social_media' => ['twitter', 'github'], 'url' => ['https://t.test', 'https://g.test']])->assertOk();
        $saved = DB::table('social_links')->where('user_id', $made['user']->id)->pluck('social_media')->all();
        sort($saved);
        $this->assertSame(['github', 'twitter'], $saved);

        $this->postJson('/api/company/account-progress', ['step' => 3, 'social_media' => [['social_media' => 'myspace', 'url' => 'https://m']]])
            ->assertUnprocessable();

        $this->postJson('/api/company/account-progress', ['step' => 3])->assertOk();
        $this->assertSame(0, DB::table('social_links')->where('user_id', $made['user']->id)->count());
    }

    public function test_step_four_contact_validates_and_saves(): void
    {
        $made = $this->actingAsCompany();
        $this->postJson('/api/company/account-progress', ['step' => 4, 'phone' => '12', 'email' => 'bad'])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'email']);

        $this->postJson('/api/company/account-progress', ['step' => 4, 'phone' => '08123456', 'email' => 'hr@acme.test', 'country' => 'Indonesia', 'address' => 'Jl. A'])
            ->assertOk()->assertJsonPath('data.success', 'Company profile updated successfully.');

        $this->assertDatabaseHas('contact_infos', ['user_id' => $made['user']->id, 'phone' => '08123456', 'email' => 'hr@acme.test']);
        $this->assertDatabaseHas('companies', ['id' => $made['company'], 'profile_completion' => 1, 'country' => 'Indonesia', 'address' => 'Jl. A']);

        // kedua kali: update, bukan duplikat
        $this->postJson('/api/company/account-progress', ['step' => 'contact', 'phone' => '08999999', 'email' => 'hr2@acme.test'])->assertOk();
        $this->assertSame(1, DB::table('contact_infos')->where('user_id', $made['user']->id)->count());
        $this->assertSame('08999999', DB::table('contact_infos')->where('user_id', $made['user']->id)->value('phone'));
    }

    public function test_invalid_step_is_rejected(): void
    {
        $this->actingAsCompany();

        $this->postJson('/api/company/account-progress', ['step' => 9])->assertUnprocessable()->assertJsonValidationErrors('step');
        $this->postJson('/api/company/account-progress', [])->assertUnprocessable()->assertJsonValidationErrors('step');
    }
}
