<?php

namespace Tests\Feature\JobSeeker;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\JobSeeker\Application\Cv\CvDocument;
use Src\JobSeeker\Application\Cv\CvRenderer;
use Src\JobSeeker\Domain\CvTemplate;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/**
 * The CV generator: what a CV holds, the choices a job seeker makes, the templates an admin switches, the hourly limit, applying with a generated CV, and a real
 * PDF for each template. Most tests swap the renderer for one that records the document, so they check what is printed without parsing a PDF.
 *
 * Siti (the fixture job seeker) lives in Bandung and has 28 months as a Backend Developer at PT Lama (March 2022 to June 2024); here she also gets a degree,
 * skills, a language, a link and the protected details.
 */
final class CvGeneratorTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    /** @var object{documents: list<CvDocument>} */
    private object $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    /** Replaces the PDF renderer by one that only records the document it was given. */
    private function recordDocuments(): void
    {
        $this->renderer = new class implements CvRenderer
        {
            /** @var list<CvDocument> */
            public array $documents = [];

            public function render(CvDocument $document): string
            {
                $this->documents[] = $document;

                return '%PDF-1.4 fake';
            }
        };

        $this->app->instance(CvRenderer::class, $this->renderer);
    }

    private function lastDocument(): CvDocument
    {
        $this->assertNotSame([], $this->renderer->documents, 'No CV was rendered.');

        return $this->renderer->documents[array_key_last($this->renderer->documents)];
    }

    /** Gives Siti a degree, skills, a language, a link and the protected details. */
    private function enrichProfile(): void
    {
        $this->api('PATCH', '/me/profile', [
            'summary' => "Backend developer with five years.\nLikes clean code.",
            'birth_date' => '1998-05-10', 'gender' => 'female', 'marital_status' => 'single',
            'religion_id' => $this->referenceId('reference_religions', 'islam'), 'nationality_id' => $this->referenceId('reference_nationalities', 'indonesian'),
            'expected_salary' => 9000000, 'expected_salary_period_id' => $this->referenceId('reference_salary_periods', 'monthly'), 'whatsapp' => '081299998888', 'address_line' => 'Jl. Rahasia 1',
        ], $this->seekerToken)->assertOk();
        $this->api('POST', '/me/educations', [
            'education_level_id' => $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 3]), 'institution' => 'ITB', 'field_of_study' => 'Informatics',
            'start_year' => 2018, 'end_year' => 2022, 'gpa' => 3.5,
        ], $this->seekerToken)->assertCreated();
        $this->api('PUT', '/me/skills', ['skill_ids' => [$this->skillId('php'), $this->skillId('laravel')]], $this->seekerToken)->assertOk();
        $this->api('PUT', '/me/languages', ['languages' => [['language_id' => $this->referenceId('reference_languages', 'english', ['code' => 'en']), 'proficiency' => 'fluent']]], $this->seekerToken)->assertOk();
        $this->api('PUT', '/me/links', ['links' => [['type' => 'github', 'url' => 'https://github.com/siti']]], $this->seekerToken)->assertOk();
    }

    /** A real picture (a PNG) as the account's profile picture. */
    private function giveAvatar(): void
    {
        $image = imagecreatetruecolor(40, 40);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 90, 200));
        ob_start();
        imagepng($image);
        Storage::put('identity/avatars/siti.png', (string) ob_get_clean());
        DB::table('identity_users')->where('id', $this->seekerId)->update(['avatar_path' => 'identity/avatars/siti.png']);
    }

    /** @param  array<string, mixed>  $body */
    private function cv(array $body = [], ?string $token = null): TestResponse
    {
        return $this->api('POST', '/me/cv', $body, $token ?? $this->seekerToken);
    }

    private function setLimit(int $perHour): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('job_seeker.cv_generations_per_hour', $perHour, null);
    }

    /** @return array{0: string, 1: int} */
    private function adminWith(array $permissions, string $email = 'ops@example.test'): array
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return [$admin->createToken('console', ['*'], now()->addDay())->plainTextToken, (int) $admin->id];
    }

    /** @param  array<string, mixed>  $body */
    private function admin(string $method, string $uri, array $body, string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->json($method, '/api/v1/admin/cv-templates'.$uri, $body);
    }

    // ----- templates and access -----

    public function test_a_job_seeker_lists_the_templates_that_are_on(): void
    {
        $response = $this->api('GET', '/me/cv/templates', token: $this->seekerToken)->assertOk();

        $this->assertSame([
            ['key' => 'classic', 'name' => 'Classic', 'is_default' => true],
            ['key' => 'modern', 'name' => 'Modern', 'is_default' => false],
            ['key' => 'compact', 'name' => 'Compact', 'is_default' => false],
        ], $response->json('data'));
    }

    public function test_only_a_signed_in_job_seeker_uses_the_generator(): void
    {
        $this->recordDocuments();

        foreach ([['GET', '/me/cv/templates'], ['POST', '/me/cv']] as [$method, $uri]) {
            $this->api($method, $uri, token: $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');
            $this->app['auth']->forgetGuards();
            $this->flushHeaders();
            $this->json($method, '/api/v1'.$uri)->assertStatus(401);
        }

        $this->assertSame([], $this->renderer->documents);
    }

    // ----- what a CV holds -----

    public function test_the_cv_holds_the_profile_and_leaves_out_the_protected_details_by_default(): void
    {
        $this->recordDocuments();
        $this->enrichProfile();

        $response = $this->cv()->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('CV Siti Rahma.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('%PDF-1.4 fake', $response->getContent());

        $cv = $this->lastDocument();
        $this->assertSame(CvTemplate::Classic, $cv->template);
        $this->assertSame('en', $cv->language);
        $this->assertSame(['Siti Rahma', 'siti@example.test', '081234567890', 'BANDUNG, JAWA BARAT'], [$cv->name, $cv->email, $cv->phone, $cv->location]);
        $this->assertSame('Backend developer', $cv->headline);
        $this->assertSame("Backend developer with five years.\nLikes clean code.", $cv->summary);
        $this->assertSame([['title' => 'Backend Developer', 'company' => 'PT Lama', 'location' => null, 'period' => 'Mar 2022 - Jun 2024', 'description' => null]], $cv->experiences);
        $this->assertSame([['title' => 'bachelor - Informatics', 'institution' => 'ITB', 'period' => '2018 - 2022', 'gpa' => 'GPA 3.50', 'description' => null]], $cv->educations);
        $this->assertSame(['php', 'laravel'], $cv->skills);
        $this->assertSame([['name' => 'english', 'level' => 'Fluent']], $cv->languages);
        $this->assertSame([['label' => 'GitHub', 'url' => 'https://github.com/siti']], $cv->links);
        // Nothing sensitive unless it was asked for: no photo, no birth date, gender, religion, marital status or nationality.
        $this->assertNull($cv->photo);
        $this->assertSame([], $cv->personal);
    }

    public function test_the_job_seeker_chooses_which_personal_details_to_print(): void
    {
        $this->recordDocuments();
        $this->enrichProfile();
        $this->giveAvatar();

        $this->cv(['include' => ['photo' => true, 'birth_date' => true, 'gender' => true, 'religion' => true, 'marital_status' => true, 'nationality' => true]])->assertOk();

        $cv = $this->lastDocument();
        $this->assertSame([
            ['label' => 'Date of birth', 'value' => '10 May 1998'],
            ['label' => 'Gender', 'value' => 'Female'],
            ['label' => 'Religion', 'value' => 'islam'],
            ['label' => 'Marital status', 'value' => 'Single'],
            ['label' => 'Nationality', 'value' => 'indonesian'],
        ], $cv->personal);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $cv->photo);

        // Only what is ticked: a detail that is false or left out stays out.
        $this->cv(['include' => ['gender' => true, 'religion' => false]])->assertOk();
        $this->assertSame([['label' => 'Gender', 'value' => 'Female']], $this->lastDocument()->personal);
        $this->assertNull($this->lastDocument()->photo);
    }

    public function test_a_photo_is_left_out_when_there_is_no_picture(): void
    {
        $this->recordDocuments();

        $this->cv(['include' => ['photo' => true]])->assertOk();

        $this->assertNull($this->lastDocument()->photo);
    }

    public function test_the_expected_salary_the_address_and_the_whatsapp_number_are_never_printed(): void
    {
        $this->recordDocuments();
        $this->enrichProfile();

        $this->cv(['include' => ['birth_date' => true, 'gender' => true, 'religion' => true, 'marital_status' => true, 'nationality' => true]])->assertOk();

        $printed = json_encode(get_object_vars($this->lastDocument()), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('9000000', $printed);
        $this->assertStringNotContainsString('Jl. Rahasia', $printed);
        $this->assertStringNotContainsString('081299998888', $printed);
    }

    public function test_the_language_and_the_template_are_chosen_per_cv(): void
    {
        $this->recordDocuments();
        $this->enrichProfile();

        $this->cv(['template' => 'modern', 'language' => 'id', 'include' => ['birth_date' => true, 'gender' => true, 'marital_status' => true]])->assertOk();

        $cv = $this->lastDocument();
        $this->assertSame(CvTemplate::Modern, $cv->template);
        $this->assertSame('id', $cv->language);
        $this->assertSame('Pengalaman kerja', $cv->labels['experience']);
        $this->assertSame('IPK 3.50', $cv->educations[0]['gpa']);
        $this->assertSame([
            ['label' => 'Tanggal lahir', 'value' => '10 Mei 1998'],
            ['label' => 'Jenis kelamin', 'value' => 'Perempuan'],
            ['label' => 'Status pernikahan', 'value' => 'Belum menikah'],
        ], $cv->personal);
        $this->assertSame('Lancar', $cv->languages[0]['level']);

        // Without a choice the language of the account is used.
        DB::table('identity_users')->where('id', $this->seekerId)->update(['locale' => 'id']);
        $this->cv()->assertOk();
        $this->assertSame('id', $this->lastDocument()->language);
    }

    public function test_an_open_experience_and_a_current_education_read_as_present(): void
    {
        $this->recordDocuments();
        DB::table('jobseeker_experiences')->where('user_id', $this->seekerId)->update(['is_current' => true, 'end_date' => null]);
        $this->api('POST', '/me/educations', [
            'education_level_id' => $this->referenceId('reference_education_levels', 'master', ['rank' => 4]), 'institution' => 'UI', 'start_year' => 2025, 'is_current' => true,
        ], $this->seekerToken)->assertCreated();

        $this->cv()->assertOk();

        $this->assertSame('Mar 2022 - Present', $this->lastDocument()->experiences[0]['period']);
        $this->assertSame('2025 - Present', $this->lastDocument()->educations[0]['period']);
    }

    public function test_a_profile_with_no_history_still_makes_a_cv_with_empty_sections(): void
    {
        $this->recordDocuments();
        $bare = $this->tokenFor($this->seeker('bare@example.test', name: 'Bare Bones'));

        $this->cv(token: $bare)->assertOk();

        $cv = $this->lastDocument();
        $this->assertSame(['Bare Bones', 'bare@example.test', null, null], [$cv->name, $cv->email, $cv->phone, $cv->location]);
        $this->assertSame([[], [], [], [], [], []], [$cv->experiences, $cv->educations, $cv->skills, $cv->languages, $cv->links, $cv->personal]);
    }

    public function test_the_choices_are_checked(): void
    {
        $this->recordDocuments();

        $this->cv(['template' => 'fancy'])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:template-unavailable');
        $this->cv(['language' => 'fr'])->assertStatus(422);
        $this->cv(['include' => ['salary' => true]])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-cv');
        $this->cv(['include' => ['photo' => 'maybe']])->assertStatus(422);
        $this->cv(['include' => 'photo'])->assertStatus(422);

        $this->assertSame([], $this->renderer->documents);
        $this->assertSame(0, DB::table('jobseeker_cv_generations')->count(), 'A refused request uses no slot.');
    }

    // ----- the templates an admin switches -----

    public function test_an_admin_lists_and_switches_the_templates(): void
    {
        [$token] = $this->adminWith(['cv_templates.manage']);

        $this->admin('GET', '', [], $token)->assertOk()->assertExactJson(['data' => [
            ['key' => 'classic', 'name' => 'Classic', 'enabled' => true, 'is_default' => true],
            ['key' => 'modern', 'name' => 'Modern', 'enabled' => true, 'is_default' => false],
            ['key' => 'compact', 'name' => 'Compact', 'enabled' => true, 'is_default' => false],
        ]]);

        // Another default.
        $this->admin('PATCH', '/compact', ['is_default' => true], $token)->assertOk()->assertJsonPath('data.is_default', true);
        $this->assertSame(['compact'], array_column(array_filter($this->admin('GET', '', [], $token)->json('data'), fn (array $row): bool => $row['is_default']), 'key'));

        // A template that is off is no longer offered and cannot be used.
        $this->admin('PATCH', '/modern', ['enabled' => false], $token)->assertOk()->assertJsonPath('data.enabled', false);
        $this->assertSame(['classic', 'compact'], array_column($this->api('GET', '/me/cv/templates', token: $this->seekerToken)->json('data'), 'key'));
        $this->recordDocuments();
        $this->cv(['template' => 'modern'])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:template-unavailable');

        // Without a choice the default is used.
        $this->cv()->assertOk();
        $this->assertSame(CvTemplate::Compact, $this->lastDocument()->template);
    }

    public function test_switching_off_the_default_hands_it_on_and_the_last_template_stays_on(): void
    {
        [$token] = $this->adminWith(['cv_templates.manage']);

        $this->admin('PATCH', '/classic', ['enabled' => false], $token)->assertOk()->assertJsonPath('data.is_default', false);
        $rows = collect($this->admin('GET', '', [], $token)->json('data'))->keyBy('key');
        $this->assertTrue($rows['modern']['is_default']);
        $this->assertFalse($rows['classic']['is_default']);

        $this->admin('PATCH', '/compact', ['enabled' => false], $token)->assertOk();
        $this->admin('PATCH', '/modern', ['enabled' => false], $token)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:cv-template-required');
        // The default must be on.
        $this->admin('PATCH', '/classic', ['is_default' => true], $token)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:cv-template-required');
        $this->assertSame(['modern'], array_column($this->api('GET', '/me/cv/templates', token: $this->seekerToken)->json('data'), 'key'));
    }

    public function test_the_admin_endpoints_check_the_permission_the_key_and_the_body(): void
    {
        [$token] = $this->adminWith(['cv_templates.manage']);
        [$other] = $this->adminWith([], 'other@example.test');

        $this->admin('GET', '', [], $other)->assertForbidden();
        $this->admin('PATCH', '/classic', ['enabled' => false], $other)->assertForbidden();
        $this->admin('GET', '', [], $this->seekerToken)->assertStatus(401);
        $this->admin('PATCH', '/fancy', ['enabled' => true], $token)->assertNotFound();
        $this->admin('PATCH', '/classic', [], $token)->assertStatus(422);
        $this->admin('PATCH', '/classic', ['enabled' => 'sometimes'], $token)->assertStatus(422);
        $this->assertTrue((bool) DB::table('jobseeker_cv_templates')->where('key', 'classic')->value('enabled'));
    }

    public function test_a_template_without_a_stored_state_counts_as_on(): void
    {
        DB::table('jobseeker_cv_templates')->delete();

        $response = $this->api('GET', '/me/cv/templates', token: $this->seekerToken)->assertOk();

        $this->assertSame(['classic', 'modern', 'compact'], array_column($response->json('data'), 'key'));
        // With nothing stored the first template is the default.
        $this->assertSame([true, false, false], array_column($response->json('data'), 'is_default'));
    }

    // ----- the hourly limit -----

    public function test_a_job_seeker_may_have_a_limited_number_of_cvs_per_hour(): void
    {
        $this->recordDocuments();
        $this->setLimit(2);

        $this->cv()->assertOk();
        $this->cv()->assertOk();
        $this->cv()->assertStatus(429)->assertJsonPath('type', 'urn:jobportal:error:cv-limit');
        $this->assertCount(2, $this->renderer->documents);

        // Someone else has their own count.
        $other = $this->tokenFor($this->seeker('other@example.test'));
        $this->cv(token: $other)->assertOk();

        // The hour moves on.
        Carbon::setTestNow('2026-10-05 10:01:00');
        $this->cv()->assertOk();
        $this->assertSame(1, DB::table('jobseeker_cv_generations')->where('user_id', $this->seekerId)->where('created_at', '>', '2026-10-05 10:00:00')->count());
    }

    public function test_the_limit_is_an_admin_setting_with_a_default_of_ten(): void
    {
        $this->recordDocuments();

        for ($i = 0; $i < 10; $i++) {
            $this->cv()->assertOk();
        }

        $this->cv()->assertStatus(429);
        $this->setLimit(11);
        $this->cv()->assertOk();
    }

    public function test_old_records_are_deleted_when_a_new_one_is_added(): void
    {
        $this->recordDocuments();
        $other = (int) UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Other', 'email' => 'other-cv@example.test', 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00'])->id;
        DB::table('jobseeker_cv_generations')->insert([
            ['user_id' => $this->seekerId, 'created_at' => '2026-10-03 09:00:00'],
            ['user_id' => $other, 'created_at' => '2026-10-03 09:00:00'],
        ]);

        $this->cv()->assertOk();

        $this->assertSame(1, DB::table('jobseeker_cv_generations')->where('user_id', $this->seekerId)->count());
        $this->assertSame(1, DB::table('jobseeker_cv_generations')->where('user_id', $other)->count(), 'Another user\'s records are not touched.');
    }

    // ----- applying with a generated CV -----

    private function applicationRow(): ?object
    {
        return DB::table('recruitment_applications')->where('user_id', $this->seekerId)->first();
    }

    public function test_an_application_can_carry_a_cv_generated_at_that_moment(): void
    {
        $this->recordDocuments();
        $job = $this->liveJob();

        $this->apply($job, ['resume_id' => null, 'cv' => ['template' => 'modern', 'language' => 'id', 'include' => ['gender' => true]]])->assertCreated();

        $row = $this->applicationRow();
        $this->assertSame('CV Siti Rahma.pdf', $row->resume_name);
        $this->assertSame('%PDF-1.4 fake', Storage::get($row->resume_path));
        $this->assertSame(13, (int) $row->resume_size_bytes);
        $this->assertCount(1, $this->renderer->documents);
        $this->assertSame(CvTemplate::Modern, $this->lastDocument()->template);
        $this->assertSame([['label' => 'Jenis kelamin', 'value' => 'Perempuan']], $this->lastDocument()->personal);
        $this->assertSame(1, DB::table('jobseeker_cv_generations')->count());
        // The profile snapshot is the same as with an uploaded resume: no gender in it.
        $this->assertArrayNotHasKey('gender', json_decode((string) $row->profile_snapshot, true));
    }

    public function test_a_job_seeker_without_an_uploaded_resume_can_apply_with_a_generated_cv(): void
    {
        $this->recordDocuments();
        DB::table('jobseeker_resumes')->where('user_id', $this->seekerId)->delete();
        $job = $this->liveJob();

        $this->apply($job, ['resume_id' => $this->resumeId])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:profile-incomplete');
        $this->apply($job, ['resume_id' => null, 'cv' => []])->assertCreated();

        $this->assertSame('CV Siti Rahma.pdf', $this->applicationRow()->resume_name);
    }

    public function test_an_application_takes_a_resume_or_a_cv_but_not_both_and_not_neither(): void
    {
        $this->recordDocuments();
        $job = $this->liveJob();

        $this->apply($job, ['resume_id' => $this->resumeId, 'cv' => ['template' => 'modern']])->assertStatus(422);
        $this->apply($job, ['resume_id' => null])->assertStatus(422);
        $this->apply($job, ['resume_id' => null, 'cv' => ['language' => 'fr']])->assertStatus(422);

        $this->assertNull($this->applicationRow());
        $this->assertSame([], $this->renderer->documents);
        // A resume alone still works, and makes no CV.
        $this->apply($job)->assertCreated();
        $this->assertSame([], $this->renderer->documents);
    }

    public function test_a_cv_that_cannot_be_made_stops_the_application(): void
    {
        $this->recordDocuments();
        $job = $this->liveJob();

        // A template that is off.
        DB::table('jobseeker_cv_templates')->where('key', 'modern')->update(['enabled' => false]);
        $this->apply($job, ['resume_id' => null, 'cv' => ['template' => 'modern']])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:template-unavailable');

        // The hourly limit.
        $this->setLimit(1);
        $this->cv()->assertOk();
        $this->apply($job, ['resume_id' => null, 'cv' => []])->assertStatus(429)->assertJsonPath('type', 'urn:jobportal:error:cv-limit');

        $this->assertNull($this->applicationRow());
        $this->assertSame([], Storage::allFiles('recruitment/applications'));
        $this->assertCount(1, $this->renderer->documents);
    }

    public function test_a_profile_that_is_not_complete_gets_no_cv_for_an_application(): void
    {
        $this->recordDocuments();
        DB::table('jobseeker_profiles')->where('user_id', $this->seekerId)->update(['phone' => null]);

        $this->apply($this->liveJob(), ['resume_id' => null, 'cv' => []])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:profile-incomplete')->assertJsonPath('missing', ['phone']);

        $this->assertSame([], $this->renderer->documents);
        $this->assertSame(0, DB::table('jobseeker_cv_generations')->count());
    }

    public function test_a_slot_is_not_used_when_the_application_fails_afterwards(): void
    {
        $this->recordDocuments();
        $job = $this->liveJob();
        $this->apply($job)->assertCreated();

        // Applying twice to the same job is refused before any CV is made.
        $this->apply($job, ['resume_id' => null, 'cv' => []])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:already-applied');

        $this->assertSame([], $this->renderer->documents);
        $this->assertSame(0, DB::table('jobseeker_cv_generations')->count());
    }

    // ----- real PDFs -----

    public function test_every_template_makes_a_real_pdf(): void
    {
        $this->enrichProfile();
        $this->giveAvatar();
        DB::table('jobseeker_experiences')->where('user_id', $this->seekerId)->update([
            'description' => str_repeat("Built and ran APIs with Laravel and MySQL. Café résumé naïve façade.\n", 25), 'location_text' => 'Bandung',
        ]);

        foreach (['classic', 'modern', 'compact'] as $template) {
            foreach (['en', 'id'] as $language) {
                $response = $this->cv([
                    'template' => $template, 'language' => $language,
                    'include' => ['photo' => true, 'birth_date' => true, 'gender' => true, 'religion' => true, 'marital_status' => true, 'nationality' => true],
                ])->assertOk();

                $pdf = (string) $response->getContent();
                $this->assertStringStartsWith('%PDF-', $pdf, "{$template} {$language}");
                $this->assertGreaterThan(5000, strlen($pdf), "{$template} {$language}");
                $this->assertStringContainsString('%%EOF', $pdf);
            }
        }
    }

    public function test_the_templates_escape_what_the_job_seeker_wrote(): void
    {
        $this->enrichProfile();
        $this->api('PATCH', '/me/profile', ['headline' => '<script>alert(1)</script>', 'summary' => '<b>bold</b> & <img src="http://evil.example/x.png">'], $this->seekerToken)->assertOk();
        DB::table('jobseeker_experiences')->where('user_id', $this->seekerId)->update(['job_title' => '<i>Dev</i>', 'description' => "<u>line</u>\nsecond"]);
        $this->recordDocuments();
        $this->cv()->assertOk();
        $document = $this->lastDocument();

        foreach (CvTemplate::cases() as $template) {
            $html = view('cv.'.$template->value, ['cv' => new CvDocument(
                $template, $document->language, $document->labels, $document->name, $document->email, $document->phone, $document->location, $document->headline,
                $document->summary, null, $document->personal, $document->experiences, $document->educations, $document->skills, $document->languages, $document->links,
            )])->render();

            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html, $template->value);
            $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt; &amp; &lt;img src=&quot;http://evil.example/x.png&quot;&gt;', $html, $template->value);
            $this->assertStringContainsString('&lt;i&gt;Dev&lt;/i&gt;', $html, $template->value);
            $this->assertStringContainsString('&lt;u&gt;line&lt;/u&gt;<br />', $html, $template->value);
            $this->assertStringNotContainsString('<script', $html, $template->value);
            $this->assertStringNotContainsString('<img src="http://evil', $html, $template->value);
        }
    }

    // ----- erasure -----

    public function test_anonymising_an_account_deletes_its_generation_records_only(): void
    {
        $this->recordDocuments();
        $other = $this->seeker('other@example.test');
        $this->cv()->assertOk();
        $this->cv(token: $this->tokenFor($other))->assertOk();

        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        $this->assertSame([$other], DB::table('jobseeker_cv_generations')->pluck('user_id')->map(fn ($id): int => (int) $id)->all());
    }
}
