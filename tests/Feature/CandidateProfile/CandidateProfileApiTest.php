<?php

namespace Tests\Feature\CandidateProfile;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\CandidateProfile\Application\CandidateProfileProjection;
use Src\Shared\Application\Contracts\UserProfileProjection;

class CandidateProfileApiTest extends CandidateTestCase
{
    /** @return array<string,array{0:string,1:string}> */
    public static function protectedEndpoints(): array
    {
        return [
            'profile' => ['GET', '/api/candidate'],
            'dashboard' => ['GET', '/api/candidate/dashboard'],
            'settings get' => ['GET', '/api/candidate/settings?type=personal'],
            'settings post' => ['POST', '/api/candidate/settings'],
            'resumes' => ['GET', '/api/candidate/resumes'],
            'resume show' => ['GET', '/api/candidate/resumes/1'],
            'upload' => ['POST', '/api/candidate/upload-resume'],
            'update resume' => ['POST', '/api/candidate/update-resume/1'],
            'delete resume' => ['DELETE', '/api/candidate/delete-resume/1'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_requires_authentication(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized()->assertJsonStructure(['error']);
    }

    public function test_company_cannot_use_candidate_endpoints(): void
    {
        Sanctum::actingAs($this->makeUser('company'));

        $this->getJson('/api/candidate')->assertForbidden()->assertJsonStructure(['error']);
        $this->getJson('/api/candidate/dashboard')->assertForbidden();
        $this->getJson('/api/candidate/settings?type=personal')->assertForbidden();
        $this->postJson('/api/candidate/settings', ['type' => 'password'])->assertForbidden();
    }

    public function test_profile_is_created_on_first_call_and_has_old_shape(): void
    {
        $user = $this->actingCandidate();
        DB::table('contact_infos')->insert(['user_id' => $user->id, 'email' => $user->email, 'phone' => '0812']);
        DB::table('social_links')->insert(['user_id' => $user->id, 'social_media' => 'github', 'url' => 'https://github.com/x']);

        $this->assertDatabaseMissing('candidates', ['user_id' => $user->id]);

        $data = $this->getJson('/api/candidate')->assertOk()->json('data');

        $this->assertDatabaseHas('candidates', ['user_id' => $user->id]);
        $this->assertSame(
            ['id', 'username', 'email', 'role', 'name', 'first_name', 'last_name', 'role_id', 'profession_id', 'experience_id',
                'education_id', 'title', 'gender', 'website', 'cv', 'bio', 'marital_status', 'religion_id', 'birth_date',
                'cv_visibility', 'profile_complete', 'address', 'neighborhood', 'locality', 'place', 'district', 'postcode',
                'region', 'country', 'photo_url', 'received_job_alert', 'social', 'contactinfo'],
            array_keys($data),
        );
        $this->assertSame($user->username, $data['username']);
        $this->assertSame('candidate', $data['role']);
        $this->assertStringEndsWith('backend/image/default.png', $data['photo_url']);
        $this->assertSame([['social_media' => 'github', 'url' => 'https://github.com/x']], $data['social']);
        $this->assertSame(['phone' => '0812', 'secondary_phone' => null, 'email' => $user->email, 'secondary_email' => null], $data['contactinfo']);

        $this->assertSame($data['id'], $this->getJson('/api/candidate')->json('data.id'));
        $this->assertSame(1, DB::table('candidates')->where('user_id', $user->id)->count());
    }

    public function test_dashboard_counts(): void
    {
        $user = $this->actingCandidate();
        $this->makeCandidate($user, ['profile_complete' => 60]);
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $user->id, 'data' => '{}',
        ]);

        $this->getJson('/api/candidate/dashboard')->assertOk()->assertExactJson(['data' => [
            'profileComplated' => 60, 'appliedJobs' => 0, 'favoriteJobs' => 0, 'notifications' => 1,
        ]]);
    }

    public function test_projection_is_tagged_and_matches_profile_endpoint(): void
    {
        $user = $this->actingCandidate();

        $projections = collect(iterator_to_array($this->app->tagged('user-profile-projections')));
        $projection = $projections->first(fn ($p) => $p instanceof CandidateProfileProjection);

        $this->assertInstanceOf(UserProfileProjection::class, $projection);
        $this->assertSame('candidate', $projection->role());
        $this->assertSame(
            $this->getJson('/api/candidate')->json('data'),
            json_decode(json_encode($projection->project($user->id)), true),
        );
    }

    public function test_settings_get_requires_valid_type(): void
    {
        $this->actingCandidate();

        $this->getJson('/api/candidate/settings')->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->getJson('/api/candidate/settings?type=password')->assertUnprocessable();
    }

    public function test_settings_get_personal(): void
    {
        $user = $this->actingCandidate();
        $exp = $this->makeExperience('Exp-'.uniqid());
        $edu = $this->makeEducation('Edu-'.uniqid());
        $this->makeCandidate($user, ['title' => 'Dev', 'experience_id' => $exp, 'education_id' => $edu, 'birth_date' => '1995-04-05']);

        $data = $this->getJson('/api/candidate/settings?type=personal')->assertOk()->json('data');

        $this->assertSame($user->name, $data['name']);
        $this->assertSame('Dev', $data['title']);
        $this->assertSame($exp, $data['experience_id']);
        $this->assertSame($edu, $data['education_id']);
        $this->assertSame('1995-04-05', $data['date_of_birth']);
        $this->assertContains($exp, array_column($data['experience_list'], 'id'));
        $this->assertContains($edu, array_column($data['education_list'], 'id'));
        $this->assertArrayHasKey('image_url', $data);
    }

    public function test_settings_get_profile(): void
    {
        $user = $this->actingCandidate();
        $candidateId = $this->makeCandidate($user, ['gender' => 'male', 'status' => 'available_in', 'available_in' => '2030-01-02', 'bio' => 'hi']);
        $skill = $this->makeSkill('Skill-'.uniqid());
        $lang = $this->makeLanguage('Lang-'.uniqid());
        DB::table('candidate_skill')->insert(['candidate_id' => $candidateId, 'skill_id' => $skill]);
        DB::table('candidate_language')->insert(['candidate_id' => $candidateId, 'candidate_language_id' => $lang]);

        $data = $this->getJson('/api/candidate/settings?type=profile')->assertOk()->json('data');

        $this->assertSame('male', $data['gender']);
        $this->assertSame('available_in', $data['availability']);
        $this->assertSame('2030-01-02', $data['available_in']);
        $this->assertSame($skill, $data['skills'][0]['id']);
        $this->assertSame($lang, $data['languages'][0]['id']);
        $this->assertContains($skill, array_column($data['skill_list'], 'id'));
        $this->assertContains($lang, array_column($data['language_list'], 'id'));
        $this->assertArrayHasKey('profession_list', $data);
    }

    public function test_settings_get_social_and_contact(): void
    {
        $user = $this->actingCandidate();
        $candidateId = $this->makeCandidate($user, ['whatsapp_number' => '62812', 'country' => 'Indonesia', 'lat' => -6.2, 'long' => 106.8]);
        DB::table('contact_infos')->insert(['user_id' => $user->id, 'email' => 'main@example.test', 'secondary_email' => 'sec@example.test', 'phone' => '1']);
        DB::table('social_links')->insert(['user_id' => $user->id, 'social_media' => 'github', 'url' => 'https://github.com/x']);
        DB::table('candidate_job_alerts')->insert(['candidate_id' => $candidateId, 'job_role_id' => null]);

        $social = $this->getJson('/api/candidate/settings?type=social')->assertOk()->json('data');
        $this->assertSame('github', $social['social_media'][0]['social_media']);
        $this->assertSame('Github', $social['social_media_list']['github']);
        $this->assertCount(9, $social['social_media_list']);

        $contact = $this->getJson('/api/candidate/settings?type=contact')->assertOk()->json('data');
        $this->assertSame('62812', $contact['contact_info']['whatsapp_no']);
        $this->assertSame('sec@example.test', $contact['contact_info']['secondary_email']);
        $this->assertSame('main@example.test', $contact['contact_info']['email']);
        $this->assertSame('Indonesia', $contact['location']['country']);
        $this->assertEquals(-6.2, $contact['location']['latitude']);
        $this->assertCount(1, $contact['job_alerts']);
        $this->assertArrayHasKey('job_alert_role_list', $contact);
    }

    public function test_settings_contact_get_without_contact_info_row(): void
    {
        $this->actingCandidate();

        $this->getJson('/api/candidate/settings?type=contact')->assertOk()
            ->assertJsonPath('data.contact_info.phone', null);
    }

    public function test_settings_post_requires_valid_type(): void
    {
        $this->actingCandidate();

        $this->postJson('/api/candidate/settings', [])->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->postJson('/api/candidate/settings', ['type' => 'nope'])->assertUnprocessable();
    }

    public function test_update_personal_with_image(): void
    {
        $user = $this->actingCandidate();
        $candidateId = $this->makeCandidate($user, ['photo' => 'images/candidates/old.jpg']);
        $exp = $this->makeExperience('E'.uniqid());
        $edu = $this->makeEducation('D'.uniqid());

        $response = $this->post('/api/candidate/settings', [
            'type' => 'personal', 'name' => 'New Name', 'title' => 'Engineer', 'experience_id' => $exp,
            'education_id' => $edu, 'date_of_birth' => '1990-12-31', 'website' => 'https://me.test',
            'image' => UploadedFile::fake()->image('a.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $response->assertJsonPath('data.message', 'Basic Info Updated Successful!')
            ->assertJsonPath('data.data.name', 'New Name')
            ->assertJsonPath('data.data.experience_id', $exp)
            ->assertJsonPath('data.data.date_of_birth', '1990-12-31');

        $photo = DB::table('candidates')->where('id', $candidateId)->value('photo');
        $this->assertSame($this->storage->stored[0], $photo);
        $this->assertSame($photo, DB::table('users')->where('id', $user->id)->value('image'));
        $this->assertSame(['images/candidates/old.jpg'], $this->storage->deleted);
        $this->assertSame('New Name', DB::table('users')->where('id', $user->id)->value('name'));
    }

    public function test_update_personal_creates_missing_candidate_and_validates(): void
    {
        $user = $this->actingCandidate();

        $this->postJson('/api/candidate/settings', ['type' => 'personal'])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'title', 'experience_id', 'education_id', 'date_of_birth']);

        $this->postJson('/api/candidate/settings', [
            'type' => 'personal', 'name' => 'N', 'title' => 'T', 'experience_id' => 999999999,
            'education_id' => 999999999, 'date_of_birth' => 'not-a-date',
        ])->assertUnprocessable()->assertJsonValidationErrors(['experience_id', 'education_id', 'date_of_birth']);

        $this->post('/api/candidate/settings', [
            'type' => 'personal', 'name' => 'N', 'title' => 'T', 'experience_id' => $this->makeExperience('x'.uniqid()),
            'education_id' => $this->makeEducation('y'.uniqid()), 'date_of_birth' => '2000-01-01',
            'image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->assertDatabaseMissing('candidates', ['user_id' => $user->id]);
    }

    public function test_update_profile_creates_profession_and_skills_and_syncs_languages(): void
    {
        $user = $this->actingCandidate();
        $candidateId = $this->makeCandidate($user);
        $existingSkill = $this->makeSkill('Known-'.uniqid());
        $lang1 = $this->makeLanguage('L1-'.uniqid());
        $lang2 = $this->makeLanguage('L2-'.uniqid());
        DB::table('candidate_language')->insert(['candidate_id' => $candidateId, 'candidate_language_id' => $lang1]);
        $newProfession = 'Prof-'.uniqid();
        $newSkill = 'NewSkill-'.uniqid();

        $data = $this->postJson('/api/candidate/settings', [
            'type' => 'profile', 'gender' => 'female', 'marital_status' => 'single', 'profession' => $newProfession,
            'status' => 'available_in', 'available_in' => '2031-05-06', 'bio' => 'About me',
            'skills' => [(string) $existingSkill, $newSkill], 'languages' => [$lang2],
        ])->assertOk()->assertJsonPath('data.message', 'Profile Info Updated Successful!')->json('data.data');

        $professionId = DB::table('profession_translations')->where('name', $newProfession)->value('profession_id');
        $this->assertNotNull($professionId);
        $this->assertSame((int) $professionId, $data['profession_id']);
        $this->assertSame('female', $data['gender']);
        $this->assertSame('available_in', $data['availability']);
        $this->assertSame('2031-05-06', $data['available_in']);
        $this->assertSame([$lang2], array_column($data['languages'], 'id'));
        $this->assertCount(2, $data['skills']);
        $this->assertContains($existingSkill, array_column($data['skills'], 'id'));
        $this->assertContains($newSkill, array_column($data['skills'], 'name'));

        // Memakai profesi yang sama lagi tidak membuat baris baru.
        $this->postJson('/api/candidate/settings', [
            'type' => 'profile', 'gender' => 'male', 'marital_status' => 'single', 'profession' => (string) $professionId,
            'status' => 'available', 'bio' => 'b',
        ])->assertOk()->assertJsonPath('data.data.profession_id', (int) $professionId);
        $this->assertSame(1, DB::table('profession_translations')->where('name', $newProfession)->count());
    }

    public function test_update_profile_validation(): void
    {
        $this->actingCandidate();

        $this->postJson('/api/candidate/settings', ['type' => 'profile'])->assertUnprocessable()
            ->assertJsonValidationErrors(['gender', 'marital_status', 'profession', 'status', 'bio']);

        $this->postJson('/api/candidate/settings', [
            'type' => 'profile', 'gender' => 'male', 'marital_status' => 'x', 'profession' => 'p',
            'status' => 'available_in', 'bio' => 'b',
        ])->assertUnprocessable()->assertJsonValidationErrors('available_in');

        $this->postJson('/api/candidate/settings', [
            'type' => 'profile', 'gender' => 'alien', 'marital_status' => 'x', 'profession' => 'p', 'status' => 'bogus', 'bio' => 'b',
        ])->assertUnprocessable()->assertJsonValidationErrors(['gender', 'status']);
    }

    public function test_update_social_replaces_links_and_prefixes_https(): void
    {
        $user = $this->actingCandidate();
        DB::table('social_links')->insert(['user_id' => $user->id, 'social_media' => 'reddit', 'url' => 'https://reddit.com/old']);

        $data = $this->postJson('/api/candidate/settings', [
            'type' => 'social', 'social_media' => ['github', 'linkedin'], 'url' => ['www.github.com/me', 'https://linkedin.com/in/me'],
        ])->assertOk()->assertJsonPath('data.message', 'Social Link Updated Successful!')->json('data.social_media');

        $this->assertCount(2, $data);
        $this->assertSame('https://www.github.com/me', $data[0]['url']);
        $this->assertSame('linkedin', $data[1]['social_media']);
        $this->assertDatabaseMissing('social_links', ['user_id' => $user->id, 'social_media' => 'reddit']);
    }

    public function test_update_social_validation(): void
    {
        $this->actingCandidate();

        $this->postJson('/api/candidate/settings', ['type' => 'social', 'social_media' => ['github'], 'url' => ['nope']])
            ->assertUnprocessable()->assertJsonValidationErrors('url.0');
        $this->postJson('/api/candidate/settings', ['type' => 'social', 'social_media' => ['myspace'], 'url' => ['https://x.com/a']])
            ->assertUnprocessable()->assertJsonValidationErrors('social_media.0');
    }

    public function test_update_contact_creates_then_updates_contact_info(): void
    {
        $user = $this->actingCandidate();
        $candidateId = $this->makeCandidate($user);
        $payload = [
            'type' => 'contact', 'phone' => '0811', 'secondary_phone' => '0822', 'email' => 'a@example.test',
            'secondary_email' => 'b@example.test', 'whatsapp_number' => '62811', 'country' => 'Indonesia',
            'address' => 'Jl. A', 'exact_location' => 'Jakarta', 'lat' => -6.2, 'long' => 106.8,
        ];

        $this->postJson('/api/candidate/settings', $payload)->assertOk()
            ->assertJsonPath('data.message', 'Contact Information Updated Successful!')
            ->assertJsonPath('data.data.contact_info.whatsapp_no', '62811')
            ->assertJsonPath('data.data.location.exact_location', 'Jakarta');

        $this->assertDatabaseHas('contact_infos', ['user_id' => $user->id, 'phone' => '0811', 'secondary_email' => 'b@example.test']);
        $this->assertDatabaseHas('candidates', ['id' => $candidateId, 'whatsapp_number' => '62811', 'country' => 'Indonesia', 'address' => 'Jl. A']);

        $this->postJson('/api/candidate/settings', ['phone' => '0899'] + $payload)->assertOk();
        $this->assertSame(1, DB::table('contact_infos')->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('contact_infos', ['user_id' => $user->id, 'phone' => '0899']);
    }

    public function test_update_contact_validation(): void
    {
        $this->actingCandidate();

        $this->postJson('/api/candidate/settings', ['type' => 'contact', 'email' => 'bad', 'secondary_email' => 'bad2'])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone', 'email', 'secondary_email']);
    }

    public function test_change_password(): void
    {
        $user = $this->makeUser('candidate', 'old-password-1');
        Sanctum::actingAs($user);

        $this->postJson('/api/candidate/settings', [
            'type' => 'password', 'current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ])->assertStatus(400)->assertExactJson(['error' => 'Current password does not match!']);

        $this->postJson('/api/candidate/settings', [
            'type' => 'password', 'current_password' => 'old-password-1', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ])->assertOk()->assertExactJson(['data' => ['message' => 'Password Updated Successful!']]);

        $this->assertTrue(Hash::check('new-password-1', DB::table('users')->where('id', $user->id)->value('password')));
    }

    public function test_change_password_validation(): void
    {
        $this->actingCandidate();

        $this->postJson('/api/candidate/settings', ['type' => 'password'])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password', 'password', 'password_confirmation']);
        $this->postJson('/api/candidate/settings', ['type' => 'password', 'current_password' => 'x', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_delete_account(): void
    {
        $user = $this->makeUser('candidate', 'my-password-1');
        $this->makeCandidate($user);
        Sanctum::actingAs($user);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\User', 'tokenable_id' => $user->id, 'name' => 't', 'token' => hash('sha256', uniqid()),
        ]);

        $this->postJson('/api/candidate/settings', ['type' => 'account-delete'])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->postJson('/api/candidate/settings', ['type' => 'account-delete', 'current_password' => 'wrong'])
            ->assertStatus(400)->assertExactJson(['error' => 'Current password does not match!']);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->postJson('/api/candidate/settings', ['type' => 'account-delete', 'current_password' => 'my-password-1'])
            ->assertOk()->assertExactJson(['data' => ['message' => 'Account Deleted Successful!']]);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('candidates', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id, 'tokenable_type' => 'App\\Models\\User']);
    }
}
