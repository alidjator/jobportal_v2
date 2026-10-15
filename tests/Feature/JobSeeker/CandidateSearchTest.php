<?php

namespace Tests\Feature\JobSeeker;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Src\JobSeeker\Application\JobSeekerSettings;
use Src\JobSeeker\Domain\ProfileSlug;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class CandidateSearchTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    /** @var array{id: int, slug: string, token: string, resume: int} */
    private array $ana;

    /** @var array{id: int, slug: string, token: string, resume: int} */
    private array $budi;

    /** @var array{id: int, slug: string, token: string, resume: int} */
    private array $citra;

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

    /**
     * A job seeker with a complete profile (phone, location, one experience, one resume) who is findable.
     *
     * @param  array<string, mixed>  $profile  columns of the profile row that replace the defaults
     * @return array{id: int, slug: string, token: string, resume: int}
     */
    private function candidate(string $email, string $name, array $profile = []): array
    {
        $id = $this->seeker($email, name: $name);
        $token = $this->tokenFor($id);
        $resume = $this->completeProfile($token);
        DB::table('jobseeker_profiles')->where('user_id', $id)->update(['is_discoverable' => true, ...$profile]);

        return ['id' => $id, 'slug' => (string) DB::table('jobseeker_profiles')->where('user_id', $id)->value('slug'), 'token' => $token, 'resume' => $resume];
    }

    /** Ana, Budi and Citra differ in everything the filters look at. */
    private function threeCandidates(): void
    {
        $bachelor = $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]);
        $master = $this->referenceId('reference_education_levels', 'master', ['rank' => 6]);
        $monthly = $this->referenceId('reference_salary_periods', 'monthly');
        $english = $this->referenceId('reference_languages', 'english', ['code' => 'en']);
        $indonesian = $this->referenceId('reference_languages', 'indonesian', ['code' => 'id']);
        $province = DB::table('reference_provinces')->insertGetId(['bps_code' => '31', 'name' => 'DKI JAKARTA']);
        $city = DB::table('reference_cities')->insertGetId(['province_id' => $province, 'bps_code' => '3171', 'name' => 'JAKARTA SELATAN', 'type' => 'city']);

        $this->ana = $this->candidate('ana@example.test', 'Ana Putri', [
            'religion_id' => $this->referenceId('reference_religions', 'islam'), 'nationality_id' => $this->referenceId('reference_nationalities', 'indonesian'),
            'gender' => 'female', 'birth_date' => '1998-05-10', 'availability' => 'open_to_work', 'experience_level_id' => $this->referenceId('reference_experience_levels', 'fresh'),
            'expected_salary' => 5_000_000, 'expected_salary_period_id' => $monthly, 'updated_at' => '2026-10-03 00:00:00',
        ]);
        $this->budi = $this->candidate('budi.s@example.test', 'Budi Santoso', [
            'headline' => 'Data analyst', 'religion_id' => $this->referenceId('reference_religions', 'christian'), 'nationality_id' => $this->referenceId('reference_nationalities', 'singaporean'),
            'gender' => 'male', 'birth_date' => '1980-01-01', 'availability' => 'employed_open_to_offers', 'experience_level_id' => $this->referenceId('reference_experience_levels', 'senior'),
            'expected_salary' => 9_000_000, 'expected_salary_period_id' => $monthly, 'province_id' => $province, 'city_id' => $city, 'subdistrict_id' => null, 'village_id' => null,
            'postal_code_id' => null, 'updated_at' => '2026-10-04 00:00:00',
        ]);
        $this->citra = $this->candidate('citra@example.test', 'Citra Dewi', ['gender' => null, 'birth_date' => null, 'updated_at' => '2026-10-02 00:00:00']);

        DB::table('jobseeker_experiences')->where('user_id', $this->budi['id'])->update(['job_title' => 'Data Scientist']);

        $now = now();

        foreach ([[$this->ana, $bachelor], [$this->budi, $master]] as [$candidate, $level]) {
            DB::table('jobseeker_educations')->insert(['user_id' => $candidate['id'], 'education_level_id' => $level, 'institution' => 'ITB', 'start_year' => 2014, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([[$this->ana, 'php', 1], [$this->ana, 'laravel', 2], [$this->budi, 'php', 1], [$this->citra, 'laravel', 1]] as [$candidate, $skill, $position]) {
            DB::table('jobseeker_skills')->insert(['user_id' => $candidate['id'], 'skill_id' => $this->skillId($skill), 'position' => $position]);
        }

        DB::table('jobseeker_languages')->insert([
            ['user_id' => $this->ana['id'], 'language_id' => $english, 'proficiency' => 'fluent'],
            ['user_id' => $this->budi['id'], 'language_id' => $indonesian, 'proficiency' => 'native'],
        ]);
    }

    /**
     * The slugs a search finds, sorted.
     *
     * @param  array<string, mixed>  $filter
     * @return list<string>
     */
    private function allowSensitiveFilters(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(JobSeekerSettings::CANDIDATE_SENSITIVE_FILTERS, true, null);
    }

    private function found(array $filter = [], ?string $token = null): array
    {
        $slugs = $this->api('GET', '/company/candidates?'.http_build_query(['filter' => $filter, 'per_page' => 100]), token: $token)->assertOk()->json('data.*.slug');
        sort($slugs);

        return $slugs;
    }

    /**
     * @param  list<array{id: int, slug: string, token: string, resume: int}>  $candidates
     * @return list<string>
     */
    private function slugs(array $candidates): array
    {
        $slugs = array_column($candidates, 'slug');
        sort($slugs);

        return $slugs;
    }

    private function views(): int
    {
        return DB::table('jobseeker_profile_views')->count();
    }

    // ----- access -----

    public function test_only_a_member_of_an_active_company_searches_candidates(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/company/candidates')->assertUnauthorized();

        $this->api('GET', '/company/candidates', token: $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
        $this->api('GET', '/company/candidates/maple-00000001', token: $this->seekerToken)->assertForbidden();
        $this->api('GET', '/company/candidates', token: $this->recruiterToken)->assertOk();
        $this->api('GET', '/company/candidates', token: $this->adminToken)->assertOk();

        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        foreach (['/company/candidates', '/company/candidates/maple-00000001', '/company/candidates/maple-00000001/avatar'] as $uri) {
            $this->api('GET', $uri, token: $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
        }

        $this->api('GET', '/company/candidates', token: $this->otherAdminToken)->assertOk();
    }

    public function test_the_job_seeker_sees_which_companies_looked_only_as_a_job_seeker(): void
    {
        $this->api('GET', '/me/profile/views', token: $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');
        $this->api('GET', '/me/profile/views', token: $this->seekerToken)->assertOk()->assertJsonPath('meta.total', 0);
    }

    // ----- who is found -----

    public function test_only_a_discoverable_complete_active_job_seeker_is_found(): void
    {
        $found = $this->candidate('found@example.test', 'Found Person');
        $this->candidate('hidden@example.test', 'Hidden Person', ['is_discoverable' => false]);
        $suspended = $this->candidate('suspended@example.test', 'Suspended Person');
        $anonymised = $this->candidate('gone@example.test', 'Gone Person');
        $noResume = $this->candidate('noresume@example.test', 'No Resume');
        $noPhone = $this->candidate('nophone@example.test', 'No Phone', ['phone' => null]);
        $noLocation = $this->candidate('noplace@example.test', 'No Place', ['city_id' => null]);
        $noHistory = $this->candidate('nohistory@example.test', 'No History');
        $noSlug = $this->candidate('noslug@example.test', 'No Slug', ['slug' => null]);

        DB::table('identity_users')->where('id', $suspended['id'])->update(['status' => 'suspended']);
        DB::table('identity_users')->where('id', $anonymised['id'])->update(['anonymized_at' => now()]);
        DB::table('jobseeker_resumes')->where('user_id', $noResume['id'])->delete();
        DB::table('jobseeker_experiences')->where('user_id', $noHistory['id'])->delete();

        $this->assertSame([$found['slug']], $this->found());

        foreach ([$suspended, $anonymised, $noResume, $noPhone, $noLocation, $noHistory] as $candidate) {
            $this->api('GET', "/company/candidates/{$candidate['slug']}", token: $this->recruiterToken)->assertNotFound();
        }

        $this->api('GET', '/company/candidates/'.$noSlug['id'], token: $this->recruiterToken)->assertNotFound();
        $this->api('GET', '/company/candidates/does-not-exist', token: $this->recruiterToken)->assertNotFound();
        $this->assertSame(0, $this->views());
    }

    public function test_the_results_are_the_most_recently_updated_profile_first_and_paged(): void
    {
        $this->threeCandidates();

        $this->assertSame([$this->budi['slug'], $this->ana['slug'], $this->citra['slug']], $this->api('GET', '/company/candidates')->assertOk()->json('data.*.slug'));

        $this->api('GET', '/company/candidates?per_page=2&page=2')->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.slug', $this->citra['slug'])->assertJsonCount(1, 'data');
        $this->api('GET', '/company/candidates?per_page=101')->assertStatus(422);
    }

    public function test_a_row_of_the_list_has_no_contact_data_or_protected_attribute(): void
    {
        $this->threeCandidates();
        DB::table('identity_users')->where('id', $this->ana['id'])->update(['avatar_path' => 'identity/avatars/ana.png']);

        $row = collect($this->api('GET', '/company/candidates')->assertOk()->json('data'))->firstWhere('slug', $this->ana['slug']);

        $this->assertSame([
            'slug' => $this->ana['slug'], 'name' => 'Ana Putri', 'has_avatar' => true, 'headline' => 'Backend developer',
            'province' => ['id' => DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->value('province_id'), 'name' => 'JAWA BARAT'],
            'city' => ['id' => DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->value('city_id'), 'name' => 'BANDUNG'],
            'experience_level' => ['id' => $this->referenceId('reference_experience_levels', 'fresh'), 'name' => 'fresh'],
            'availability' => 'open_to_work',
            'highest_education' => ['id' => $this->referenceId('reference_education_levels', 'bachelor'), 'name' => 'bachelor'],
            'updated_at' => '2026-10-03T00:00:00+00:00',
        ], $row);
        $this->assertFalse(collect($this->api('GET', '/company/candidates')->json('data'))->firstWhere('slug', $this->citra['slug'])['has_avatar']);
    }

    // ----- filters -----

    public function test_the_keyword_looks_in_the_headline_the_summary_and_the_job_titles(): void
    {
        $this->threeCandidates();
        DB::table('jobseeker_profiles')->where('user_id', $this->citra['id'])->update(['headline' => 'Designer', 'summary' => 'I love rust and 100% quality']);

        // Citra's job title is still Backend Developer.
        $this->assertSame($this->slugs([$this->ana, $this->citra]), $this->found(['q' => 'backend']));
        $this->assertSame($this->slugs([$this->budi]), $this->found(['q' => 'Data']));
        $this->assertSame($this->slugs([$this->budi]), $this->found(['q' => 'scientist']));
        $this->assertSame($this->slugs([$this->citra]), $this->found(['q' => 'rust']));
        $this->assertSame($this->slugs([$this->citra]), $this->found(['q' => '100%']));
        $this->assertSame([], $this->found(['q' => '%%']));
    }

    public function test_skills_and_languages_ask_for_all_of_them(): void
    {
        $this->threeCandidates();
        $php = $this->skillId('php');
        $laravel = $this->skillId('laravel');

        $this->assertSame($this->slugs([$this->ana, $this->budi]), $this->found(['skill_id' => [$php]]));
        $this->assertSame($this->slugs([$this->ana, $this->citra]), $this->found(['skill_id' => [$laravel]]));
        $this->assertSame($this->slugs([$this->ana]), $this->found(['skill_id' => [$php, $laravel]]));
        $this->assertSame($this->slugs([$this->ana]), $this->found(['language_id' => [$this->referenceId('reference_languages', 'english')]]));
        $this->assertSame([], $this->found(['language_id' => [$this->referenceId('reference_languages', 'english'), $this->referenceId('reference_languages', 'indonesian')]]));
    }

    public function test_the_location_the_experience_level_and_the_education_level_filter(): void
    {
        $this->threeCandidates();
        $budiProfile = DB::table('jobseeker_profiles')->where('user_id', $this->budi['id'])->first();
        $anaProfile = DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->first();

        $this->assertSame($this->slugs([$this->budi]), $this->found(['province_id' => $budiProfile->province_id]));
        $this->assertSame($this->slugs([$this->budi]), $this->found(['city_id' => $budiProfile->city_id]));
        $this->assertSame($this->slugs([$this->ana, $this->citra]), $this->found(['province_id' => $anaProfile->province_id, 'city_id' => $anaProfile->city_id]));

        $fresh = $this->referenceId('reference_experience_levels', 'fresh');
        $senior = $this->referenceId('reference_experience_levels', 'senior');
        $this->assertSame($this->slugs([$this->ana]), $this->found(['experience_level_id' => [$fresh]]));
        $this->assertSame($this->slugs([$this->ana, $this->budi]), $this->found(['experience_level_id' => [$fresh, $senior]]));

        // This level or higher: a master degree satisfies a bachelor minimum.
        $this->assertSame($this->slugs([$this->ana, $this->budi]), $this->found(['education_level_id' => $this->referenceId('reference_education_levels', 'bachelor')]));
        $this->assertSame($this->slugs([$this->budi]), $this->found(['education_level_id' => $this->referenceId('reference_education_levels', 'master')]));
    }

    public function test_availability_and_expected_salary_filter(): void
    {
        $this->threeCandidates();
        $monthly = $this->referenceId('reference_salary_periods', 'monthly');

        $this->assertSame($this->slugs([$this->ana]), $this->found(['availability' => ['open_to_work']]));
        $this->assertSame($this->slugs([$this->ana, $this->budi]), $this->found(['availability' => ['open_to_work', 'employed_open_to_offers']]));

        // A candidate who gave no expectation does not match a maximum.
        $this->assertSame($this->slugs([$this->ana]), $this->found(['expected_salary_max' => 6_000_000, 'salary_period_id' => $monthly]));
        $this->assertSame($this->slugs([$this->ana, $this->budi]), $this->found(['expected_salary_max' => 9_000_000, 'salary_period_id' => $monthly]));
        $this->assertSame([], $this->found(['expected_salary_max' => 9_000_000, 'salary_period_id' => $this->referenceId('reference_salary_periods', 'yearly')]));
    }

    public function test_the_sensitive_filters_are_refused_until_an_admin_switches_them_on(): void
    {
        $this->threeCandidates();

        foreach ([['religion_id' => [$this->referenceId('reference_religions', 'islam')]], ['nationality_id' => [$this->referenceId('reference_nationalities', 'indonesian')]], ['age_min' => 20], ['age_max' => 40], ['gender' => 'female']] as $filter) {
            $this->api('GET', '/company/candidates?'.http_build_query(['filter' => $filter]), token: $this->adminToken)
                ->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:filter-not-available');
        }

        // The other filters are not affected.
        $this->assertSame($this->slugs([$this->ana]), $this->found(['availability' => ['open_to_work']]));

        $this->allowSensitiveFilters();
        $this->assertSame($this->slugs([$this->ana]), $this->found(['gender' => 'female']));
    }

    public function test_religion_nationality_age_and_gender_filter(): void
    {
        $this->allowSensitiveFilters();
        $this->threeCandidates();

        $this->assertSame($this->slugs([$this->ana]), $this->found(['religion_id' => [$this->referenceId('reference_religions', 'islam')]]));
        $this->assertSame($this->slugs([$this->budi]), $this->found(['nationality_id' => [$this->referenceId('reference_nationalities', 'singaporean')]]));
        $this->assertSame($this->slugs([$this->ana]), $this->found(['gender' => 'female']));
        $this->assertSame($this->slugs([$this->budi]), $this->found(['gender' => 'male']));

        // 2026-10-05 in Jakarta: Ana (born 1998-05-10) is 28, Budi (born 1980-01-01) is 46, Citra gave no birth date.
        $this->assertSame($this->slugs([$this->budi]), $this->found(['age_min' => 30]));
        $this->assertSame($this->slugs([$this->ana, $this->budi]), $this->found(['age_min' => 28]));
        $this->assertSame($this->slugs([$this->ana]), $this->found(['age_max' => 28]));
        $this->assertSame([], $this->found(['age_max' => 27]));
        $this->assertSame($this->slugs([$this->ana]), $this->found(['age_min' => 28, 'age_max' => 28]));
        $this->assertSame($this->slugs([$this->budi]), $this->found(['age_min' => 46, 'age_max' => 46]));
        $this->assertSame([], $this->found(['age_min' => 47]));
    }

    public function test_filters_combine(): void
    {
        $this->allowSensitiveFilters();
        $this->threeCandidates();

        $this->assertSame($this->slugs([$this->ana]), $this->found(['skill_id' => [$this->skillId('php')], 'gender' => 'female', 'q' => 'backend']));
        $this->assertSame([], $this->found(['skill_id' => [$this->skillId('php')], 'gender' => 'male', 'q' => 'backend']));
    }

    public function test_the_filters_are_validated(): void
    {
        $monthly = $this->referenceId('reference_salary_periods', 'monthly');

        foreach ([
            ['unknown' => 1], ['q' => 'a'], ['skill_id' => 'php'], ['skill_id' => [0]], ['availability' => ['sleeping']], ['gender' => 'other'], ['age_min' => 12], ['age_min' => 40, 'age_max' => 30],
            ['expected_salary_max' => 1_000_000], ['salary_period_id' => $monthly], ['province_id' => 'x'], ['skill_id' => range(1, 11)],
        ] as $filter) {
            $this->api('GET', '/company/candidates?'.http_build_query(['filter' => $filter]))->assertStatus(422);
        }
    }

    // ----- the profile -----

    public function test_a_profile_shows_what_a_company_may_see_and_no_contact_data(): void
    {
        $this->threeCandidates();
        $slug = $this->ana['slug'];
        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['summary' => 'Five years of PHP.', 'available_from' => '2026-10-20', 'address_line' => 'Jl. Merdeka 1', 'whatsapp' => '081200000000']);
        DB::table('jobseeker_links')->insert(['user_id' => $this->ana['id'], 'type' => 'github', 'url' => 'https://github.com/ana']);

        $response = $this->api('GET', "/company/candidates/{$slug}", token: $this->recruiterToken)->assertOk();
        $response->assertJsonPath('data.slug', $slug)
            ->assertJsonPath('data.name', 'Ana Putri')
            ->assertJsonPath('data.headline', 'Backend developer')
            ->assertJsonPath('data.summary', 'Five years of PHP.')
            ->assertJsonPath('data.province.name', 'JAWA BARAT')
            ->assertJsonPath('data.city.name', 'BANDUNG')
            ->assertJsonPath('data.experience_level.name', 'fresh')
            ->assertJsonPath('data.availability', 'open_to_work')
            ->assertJsonPath('data.available_from', '2026-10-20')
            ->assertJsonPath('data.expected_salary.amount', 5_000_000)
            ->assertJsonPath('data.expected_salary.period.name', 'monthly')
            ->assertJsonPath('data.experiences.0.job_title', 'Backend Developer')
            ->assertJsonPath('data.educations.0.level.name', 'bachelor')
            ->assertJsonPath('data.educations.0.institution', 'ITB')
            ->assertJsonPath('data.skills.*.name', ['php', 'laravel'])
            ->assertJsonPath('data.languages.0.name', 'english')
            ->assertJsonPath('data.languages.0.proficiency', 'fluent')
            ->assertJsonPath('data.contact', null);

        foreach (['email', 'phone', 'whatsapp', 'address_line', 'links', 'gender', 'religion', 'religion_id', 'nationality', 'nationality_id', 'marital_status', 'birth_date', 'age'] as $field) {
            $response->assertJsonMissingPath("data.{$field}");
        }

        $this->assertStringNotContainsString('ana@example.test', $response->getContent());
        $this->assertStringNotContainsString('081234567890', $response->getContent());
        $this->assertStringNotContainsString('Jl. Merdeka', $response->getContent());
    }

    public function test_the_contact_data_appears_for_a_member_who_sees_an_application_of_the_candidate(): void
    {
        $this->threeCandidates();
        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['whatsapp' => '081200000000', 'address_line' => 'Jl. Merdeka 1']);
        DB::table('jobseeker_links')->insert(['user_id' => $this->ana['id'], 'type' => 'github', 'url' => 'https://github.com/ana']);
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->apply($job, ['resume_id' => $this->ana['resume']], $this->ana['token'])->assertCreated();

        $contact = [
            'email' => 'ana@example.test', 'phone' => '081234567890', 'whatsapp' => '081200000000', 'address_line' => 'Jl. Merdeka 1',
            'links' => [['type' => 'github', 'url' => 'https://github.com/ana']],
        ];

        // The company admin sees every applicant and the assigned recruiter theirs; the other recruiter sees neither the application nor the contact.
        $this->api('GET', "/company/candidates/{$this->ana['slug']}", token: $this->adminToken)->assertOk()->assertJsonPath('data.contact', $contact);
        $this->api('GET', "/company/candidates/{$this->ana['slug']}", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.contact', $contact);
        $this->api('GET', "/company/candidates/{$this->ana['slug']}", token: $this->secondRecruiterToken)->assertOk()->assertJsonPath('data.contact', null);
        $this->api('GET', "/company/candidates/{$this->ana['slug']}", token: $this->otherAdminToken)->assertOk()->assertJsonPath('data.contact', null);
        $this->api('GET', "/company/candidates/{$this->budi['slug']}", token: $this->adminToken)->assertOk()->assertJsonPath('data.contact', null);
    }

    // ----- the view log -----

    public function test_opening_a_profile_is_logged_once_per_member_and_day_and_the_candidate_sees_the_companies(): void
    {
        $this->threeCandidates();
        $slug = $this->ana['slug'];

        $this->found();
        $this->assertSame(0, $this->views());

        $this->api('GET', "/company/candidates/{$slug}", token: $this->recruiterToken)->assertOk();
        $this->api('GET', "/company/candidates/{$slug}", token: $this->recruiterToken)->assertOk();
        $this->assertSame(1, $this->views());

        $this->api('GET', "/company/candidates/{$slug}", token: $this->adminToken)->assertOk();
        $this->api('GET', "/company/candidates/{$this->budi['slug']}", token: $this->adminToken)->assertOk();
        $this->assertSame(3, $this->views());
        $this->assertSame(
            [$this->recruiterId, $this->adminId], DB::table('jobseeker_profile_views')->where('user_id', $this->ana['id'])->orderBy('id')->pluck('member_id')->map(fn ($id): int => (int) $id)->all(),
        );
        $this->assertSame([$this->companyId], DB::table('jobseeker_profile_views')->where('user_id', $this->ana['id'])->pluck('company_id')->unique()->map(fn ($id): int => (int) $id)->values()->all());

        // Another day, another entry.
        Carbon::setTestNow('2026-10-06 08:00:00');
        $this->api('GET', "/company/candidates/{$slug}", token: $this->recruiterToken)->assertOk();
        $this->assertSame(4, $this->views());

        // Ana sees the companies, not the members: two members of one company on the first day make one entry.
        $this->api('GET', '/me/profile/views', token: $this->ana['token'])->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.company.id', $this->companyId)
            ->assertJsonPath('data.0.kind', 'profile')
            ->assertJsonPath('data.0.day', '2026-10-06')
            ->assertJsonPath('data.1.day', '2026-10-05')
            ->assertJsonMissingPath('data.0.member_id')
            ->assertJsonMissingPath('data.0.member');
        $this->api('GET', '/me/profile/views', token: $this->budi['token'])->assertOk()->assertJsonPath('meta.total', 1);
        $this->api('GET', '/me/profile/views', token: $this->citra['token'])->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_a_member_may_open_only_so_many_different_profiles_a_day(): void
    {
        $this->threeCandidates();
        $this->app->make(SettingsStore::class)->setGlobal(JobSeekerSettings::CANDIDATE_VIEWS_PER_DAY, 10, null);

        // Nine other profiles were opened today and Ana's is the tenth.
        foreach (range(1, 9) as $userId) {
            DB::table('jobseeker_profile_views')->insert(['user_id' => 1000 + $userId, 'company_id' => $this->companyId, 'member_id' => $this->recruiterId, 'kind' => 'profile', 'day' => '2026-10-05', 'created_at' => now()]);
        }

        $this->api('GET', "/company/candidates/{$this->ana['slug']}", token: $this->recruiterToken)->assertOk();
        $this->assertSame(10, DB::table('jobseeker_profile_views')->where('member_id', $this->recruiterId)->count());

        $this->api('GET', "/company/candidates/{$this->ana['slug']}", token: $this->recruiterToken)->assertOk();
        $this->api('GET', "/company/candidates/{$this->budi['slug']}", token: $this->recruiterToken)->assertStatus(429)->assertJsonPath('type', 'urn:jobportal:error:candidate-view-limit');
        $this->assertSame(10, DB::table('jobseeker_profile_views')->where('member_id', $this->recruiterId)->count());

        // The limit belongs to the member and to the day; the list is not limited by it.
        $this->api('GET', "/company/candidates/{$this->budi['slug']}", token: $this->adminToken)->assertOk();
        $this->assertCount(3, $this->found(token: $this->recruiterToken));
        Carbon::setTestNow('2026-10-06 08:00:00');
        $this->api('GET', "/company/candidates/{$this->budi['slug']}", token: $this->recruiterToken)->assertOk();
    }

    // ----- resumes and pictures -----

    public function test_a_resume_is_downloaded_only_when_the_candidate_allows_it_and_the_download_is_logged(): void
    {
        $this->threeCandidates();
        $slug = $this->ana['slug'];
        $resume = $this->ana['resume'];

        $this->api('GET', "/company/candidates/{$slug}", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.resumes', []);
        $this->api('GET', "/company/candidates/{$slug}/resumes/{$resume}", token: $this->recruiterToken)->assertNotFound();
        $this->assertSame(0, DB::table('jobseeker_profile_views')->where('kind', 'resume')->count());

        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['resume_discoverable' => true]);

        $this->api('GET', "/company/candidates/{$slug}", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.resumes.0.id', $resume)->assertJsonPath('data.resumes.0.name', 'Siti CV.pdf')->assertJsonMissingPath('data.resumes.0.path');

        $response = $this->api('GET', "/company/candidates/{$slug}/resumes/{$resume}", token: $this->recruiterToken)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->api('GET', "/company/candidates/{$slug}/resumes/{$resume}", token: $this->recruiterToken)->assertOk();
        $this->assertSame(1, DB::table('jobseeker_profile_views')->where('kind', 'resume')->count());
        $this->assertSame($this->recruiterId, (int) DB::table('jobseeker_profile_views')->where('kind', 'resume')->value('member_id'));

        // Each download is also an entry of the company activity log, with the candidate's slug (never an internal id).
        $entries = DB::table('platform_audit_logs')->where('action', 'candidate.resume_downloaded')->get();
        $this->assertCount(2, $entries);
        $this->assertSame(['candidate' => $slug], json_decode((string) $entries[0]->metadata, true));
        $this->assertSame($resume, (int) $entries[0]->subject_id);

        // A resume of somebody else, or one that does not exist, is not found; a missing file too.
        $this->api('GET', "/company/candidates/{$slug}/resumes/{$this->budi['resume']}", token: $this->recruiterToken)->assertNotFound();
        $this->api('GET', "/company/candidates/{$slug}/resumes/999999", token: $this->recruiterToken)->assertNotFound();
        $this->api('GET', "/company/candidates/{$slug}/resumes/{$resume}", token: $this->seekerToken)->assertForbidden();
        Storage::delete((string) DB::table('jobseeker_resumes')->where('id', $resume)->value('path'));
        $this->api('GET', "/company/candidates/{$slug}/resumes/{$resume}", token: $this->recruiterToken)->assertNotFound();
    }

    public function test_the_picture_of_a_candidate(): void
    {
        $this->threeCandidates();
        $png = "\x89PNG\r\n\x1A\n".str_repeat("\0", 32);

        $this->api('GET', "/company/candidates/{$this->ana['slug']}/avatar", token: $this->recruiterToken)->assertNotFound()->assertJsonPath('type', 'urn:jobportal:error:avatar-not-found');

        Storage::put('identity/avatars/ana.png', $png);
        DB::table('identity_users')->where('id', $this->ana['id'])->update(['avatar_path' => 'identity/avatars/ana.png']);

        $response = $this->api('GET', "/company/candidates/{$this->ana['slug']}/avatar", token: $this->recruiterToken)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($png, $response->getContent());
        $this->api('GET', "/company/candidates/{$this->ana['slug']}", token: $this->recruiterToken)->assertJsonPath('data.has_avatar', true);
        $this->api('GET', '/company/candidates/does-not-exist/avatar', token: $this->recruiterToken)->assertNotFound();

        Storage::delete('identity/avatars/ana.png');
        $this->api('GET', "/company/candidates/{$this->ana['slug']}/avatar", token: $this->recruiterToken)->assertNotFound();

        // Only the one opening of the profile was logged, no picture request.
        $this->assertSame(1, $this->views());
    }

    // ----- the slug -----

    public function test_a_profile_gets_one_permanent_random_slug_that_is_not_built_from_the_name(): void
    {
        $this->threeCandidates();
        $slugs = array_column([$this->ana, $this->budi, $this->citra], 'slug');

        foreach ($slugs as $slug) {
            $this->assertMatchesRegularExpression('/^[a-z]+-\d{8}$/', $slug);
        }

        $this->assertCount(3, array_unique($slugs));

        $this->api('PATCH', '/me/profile', ['headline' => 'Changed', 'is_discoverable' => false], $this->ana['token'])->assertOk();
        $this->api('PATCH', '/me/profile', ['headline' => 'Changed again'], $this->ana['token'])->assertOk();
        $this->assertSame($this->ana['slug'], DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->value('slug'));
        $this->assertSame(1, preg_match('/^[a-z]+-\d{8}$/', ProfileSlug::generate()));
    }

    public function test_a_profile_row_without_a_slug_gets_one_the_first_time_it_is_saved(): void
    {
        $id = $this->seeker('late@example.test');
        DB::table('jobseeker_profiles')->insert(['user_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertNull(DB::table('jobseeker_profiles')->where('user_id', $id)->value('slug'));

        $this->api('PATCH', '/me/profile', ['headline' => 'Hello'], $this->tokenFor($id))->assertOk();

        $this->assertMatchesRegularExpression('/^[a-z]+-\d{8}$/', (string) DB::table('jobseeker_profiles')->where('user_id', $id)->value('slug'));
    }

    public function test_the_slug_is_never_part_of_the_profile_of_the_owner(): void
    {
        $this->api('GET', '/me/profile', token: $this->seekerToken)->assertOk()->assertJsonMissingPath('data.slug');
    }
}
