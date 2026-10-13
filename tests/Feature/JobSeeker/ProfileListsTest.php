<?php

namespace Tests\Feature\JobSeeker;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class ProfileListsTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private int $userId;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
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

    /** @return array<string, mixed> */
    private function experience(array $overrides = []): array
    {
        return [
            'job_title' => 'Backend Developer', 'company_name' => 'PT Maju', 'start_date' => '2022-03', 'end_date' => '2024-06', 'is_current' => false, ...$overrides,
        ];
    }

    /** @return array<string, mixed> */
    private function education(array $overrides = []): array
    {
        return [
            'education_level_id' => $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]), 'institution' => 'Institut Teknologi Bandung', 'start_year' => 2014,
            'end_year' => 2018, 'is_current' => false, ...$overrides,
        ];
    }

    // ----- access -----

    public function test_every_list_needs_a_signed_in_job_seeker(): void
    {
        foreach ([['GET', '/me/experiences'], ['POST', '/me/experiences'], ['GET', '/me/educations'], ['POST', '/me/educations'], ['PUT', '/me/skills'], ['PUT', '/me/languages'], ['PUT', '/me/links']] as [$method, $uri]) {
            $this->json($method, '/api/v1'.$uri)->assertStatus(401);
        }

        $member = $this->tokenFor($this->account('company_member', 'member@example.test'));

        foreach ([['GET', '/me/experiences', []], ['POST', '/me/experiences', $this->experience()], ['GET', '/me/educations', []], ['POST', '/me/educations', $this->education()],
            ['PUT', '/me/skills', ['skill_ids' => []]], ['PUT', '/me/languages', ['languages' => []]], ['PUT', '/me/links', ['links' => []]]] as [$method, $uri, $body]) {
            $this->api($method, $uri, $body, $member)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');
        }

        $this->assertSame(0, DB::table('jobseeker_experiences')->count());
    }

    // ----- experiences -----

    public function test_an_experience_is_added_listed_changed_and_deleted(): void
    {
        $type = $this->referenceId('reference_job_types', 'full-time');
        $created = $this->api('POST', '/me/experiences', $this->experience(['job_type_id' => $type, 'location_text' => 'Bandung', 'description' => 'Built the API.']))->assertCreated();

        $created->assertJsonPath('data.job_title', 'Backend Developer')->assertJsonPath('data.start_date', '2022-03')->assertJsonPath('data.end_date', '2024-06')
            ->assertJsonPath('data.job_type_id', $type)->assertJsonPath('data.is_current', false)->assertJsonPath('data.location_text', 'Bandung');
        $id = (int) $created->json('data.id');
        $this->assertSame('2022-03-01', DB::table('jobseeker_experiences')->where('id', $id)->value('start_date'));
        $this->assertSame($this->userId, (int) DB::table('jobseeker_experiences')->where('id', $id)->value('user_id'));

        $this->api('PATCH', "/me/experiences/{$id}", ['job_title' => 'Senior Backend Developer', 'description' => null])->assertOk()
            ->assertJsonPath('data.job_title', 'Senior Backend Developer')->assertJsonPath('data.company_name', 'PT Maju')->assertJsonPath('data.description', null);
        $this->api('GET', '/me/experiences')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);

        $this->api('DELETE', "/me/experiences/{$id}")->assertNoContent();
        $this->api('GET', '/me/experiences')->assertOk()->assertExactJson(['data' => []]);
        $this->api('DELETE', "/me/experiences/{$id}")->assertStatus(404);
    }

    public function test_the_experiences_are_listed_current_first_then_by_the_latest_end(): void
    {
        $old = (int) $this->api('POST', '/me/experiences', $this->experience(['job_title' => 'Old job', 'start_date' => '2015-01', 'end_date' => '2017-01']))->json('data.id');
        $recent = (int) $this->api('POST', '/me/experiences', $this->experience(['job_title' => 'Recent job', 'start_date' => '2019-01', 'end_date' => '2023-01']))->json('data.id');
        $current = (int) $this->api('POST', '/me/experiences', $this->experience(['job_title' => 'Current job', 'start_date' => '2023-02', 'end_date' => null, 'is_current' => true]))->json('data.id');

        $this->assertSame([$current, $recent, $old], array_column($this->api('GET', '/me/experiences')->json('data'), 'id'));
    }

    public function test_the_content_of_an_experience_is_checked_as_a_whole(): void
    {
        $cases = [
            'job_title' => $this->experience(['job_title' => 'ab']),
            'company_name' => $this->experience(['company_name' => 'a']),
            'description' => $this->experience(['description' => str_repeat('a', 2001)]),
            'location_text' => $this->experience(['location_text' => str_repeat('a', 101)]),
            'start_date' => $this->experience(['start_date' => '2026-11']),
            'end_date' => $this->experience(['end_date' => '2021-12']),
        ];

        foreach ($cases as $field => $body) {
            $this->api('POST', '/me/experiences', $body)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-experience')->assertJsonStructure(['errors' => [$field]]);
        }

        $this->api('POST', '/me/experiences', $this->experience(['is_current' => true]))->assertStatus(422)->assertJsonStructure(['errors' => ['end_date']]);
        $this->api('POST', '/me/experiences', $this->experience(['end_date' => null]))->assertStatus(422)->assertJsonStructure(['errors' => ['end_date']]);
        $this->api('POST', '/me/experiences', $this->experience(['end_date' => '2026-11']))->assertStatus(422);
        $this->api('POST', '/me/experiences', ['company_name' => 'PT Maju'])->assertStatus(422);
        $this->api('POST', '/me/experiences', $this->experience(['start_date' => '2022-13']))->assertStatus(422);
        $this->api('POST', '/me/experiences', $this->experience(['job_type_id' => 999_999]))->assertStatus(422)->assertJsonStructure(['errors' => ['job_type_id']]);

        $this->assertSame(0, DB::table('jobseeker_experiences')->count());
    }

    public function test_this_month_is_the_latest_month_allowed_and_a_current_job_has_no_end(): void
    {
        $this->api('POST', '/me/experiences', $this->experience(['start_date' => '2026-10', 'end_date' => '2026-10']))->assertCreated();
        $this->api('POST', '/me/experiences', $this->experience(['job_title' => 'Current', 'start_date' => '2025-01', 'end_date' => null, 'is_current' => true]))->assertCreated()->assertJsonPath('data.end_date', null);
    }

    public function test_a_change_is_checked_together_with_what_stays(): void
    {
        $id = (int) $this->api('POST', '/me/experiences', $this->experience())->json('data.id');

        $this->api('PATCH', "/me/experiences/{$id}", ['start_date' => '2025-01'])->assertStatus(422)->assertJsonStructure(['errors' => ['end_date']]);
        $this->api('PATCH', "/me/experiences/{$id}", ['is_current' => true])->assertStatus(422)->assertJsonStructure(['errors' => ['end_date']]);
        $this->api('PATCH', "/me/experiences/{$id}", ['is_current' => true, 'end_date' => null])->assertOk()->assertJsonPath('data.is_current', true)->assertJsonPath('data.end_date', null);
        $this->api('PATCH', "/me/experiences/{$id}", ['job_type_id' => 999_999])->assertStatus(422);
        $this->assertSame('2022-03-01', DB::table('jobseeker_experiences')->where('id', $id)->value('start_date'));
    }

    public function test_at_most_thirty_experiences(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            DB::table('jobseeker_experiences')->insert([
                'user_id' => $this->userId, 'job_title' => "Job {$i}", 'company_name' => 'PT Maju', 'start_date' => '2020-01-01', 'end_date' => '2021-01-01', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->api('POST', '/me/experiences', $this->experience())->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:experience-limit-reached');
        $this->assertSame(30, DB::table('jobseeker_experiences')->count());
    }

    public function test_adding_an_entry_locks_the_profile_row_creating_an_empty_one_that_changes_nothing_visible(): void
    {
        $this->assertSame(0, DB::table('jobseeker_profiles')->count());

        $this->api('POST', '/me/experiences', $this->experience())->assertCreated();
        $this->assertSame(1, DB::table('jobseeker_profiles')->where('user_id', $this->userId)->count());
        $this->api('GET', '/me/profile')->assertOk()->assertJsonPath('data.headline', null)->assertJsonPath('data.is_discoverable', false);

        $this->api('PATCH', '/me/profile', ['headline' => 'Developer'])->assertOk();
        $this->api('POST', '/me/educations', $this->education())->assertCreated();
        $this->api('POST', '/me/experiences', $this->experience(['job_title' => 'Another job']))->assertCreated();
        $this->assertSame(1, DB::table('jobseeker_profiles')->count());
        $this->api('GET', '/me/profile')->assertJsonPath('data.headline', 'Developer');
    }

    public function test_an_invalid_entry_is_not_stored(): void
    {
        $this->api('POST', '/me/experiences', $this->experience(['job_title' => 'ab']))->assertStatus(422);
        $this->assertSame(0, DB::table('jobseeker_experiences')->count());
    }

    public function test_an_experience_of_another_account_does_not_exist(): void
    {
        $other = $this->account('job_seeker', 'budi@example.test');
        $foreign = (int) $this->api('POST', '/me/experiences', $this->experience(), $this->tokenFor($other))->json('data.id');

        $this->api('PATCH', "/me/experiences/{$foreign}", ['job_title' => 'Hijacked'])->assertStatus(404);
        $this->api('DELETE', "/me/experiences/{$foreign}")->assertStatus(404);
        $this->api('GET', '/me/experiences')->assertExactJson(['data' => []]);
        $this->assertSame('Backend Developer', DB::table('jobseeker_experiences')->where('id', $foreign)->value('job_title'));
    }

    // ----- education -----

    public function test_an_education_entry_is_added_listed_changed_and_deleted(): void
    {
        $created = $this->api('POST', '/me/educations', $this->education(['field_of_study' => 'Informatics', 'gpa' => 3.5, 'description' => 'Cum laude.']))->assertCreated();

        $created->assertJsonPath('data.institution', 'Institut Teknologi Bandung')->assertJsonPath('data.start_year', 2014)->assertJsonPath('data.end_year', 2018)
            ->assertJsonPath('data.gpa', '3.50')->assertJsonPath('data.field_of_study', 'Informatics');
        $id = (int) $created->json('data.id');

        $this->api('PATCH', "/me/educations/{$id}", ['gpa' => '3.75', 'field_of_study' => null])->assertOk()->assertJsonPath('data.gpa', '3.75')->assertJsonPath('data.field_of_study', null);
        $this->api('GET', '/me/educations')->assertOk()->assertJsonCount(1, 'data');
        $this->api('DELETE', "/me/educations/{$id}")->assertNoContent();
        $this->api('DELETE', "/me/educations/{$id}")->assertStatus(404);
    }

    public function test_someone_still_studying_may_leave_the_end_year_empty_or_give_the_expected_one(): void
    {
        $this->api('POST', '/me/educations', $this->education(['is_current' => true, 'end_year' => null, 'start_year' => 2023]))->assertCreated()->assertJsonPath('data.end_year', null);
        $this->api('POST', '/me/educations', $this->education(['institution' => 'Another university', 'is_current' => true, 'end_year' => 2030, 'start_year' => 2024]))->assertCreated()->assertJsonPath('data.end_year', 2030);
        $this->api('POST', '/me/educations', $this->education(['institution' => 'Far away', 'is_current' => true, 'end_year' => 2037, 'start_year' => 2024]))->assertStatus(422)->assertJsonStructure(['errors' => ['end_year']]);
    }

    public function test_the_content_of_an_education_entry_is_checked(): void
    {
        $cases = [
            'institution' => $this->education(['institution' => 'a']),
            'field_of_study' => $this->education(['field_of_study' => str_repeat('a', 101)]),
            'description' => $this->education(['description' => str_repeat('a', 1001)]),
            'start_year' => $this->education(['start_year' => 1949]),
            'end_year' => $this->education(['end_year' => 2013]),
            'gpa' => $this->education(['gpa' => 4.5]),
        ];

        foreach ($cases as $field => $body) {
            $this->api('POST', '/me/educations', $body)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-education')->assertJsonStructure(['errors' => [$field]]);
        }

        $this->api('POST', '/me/educations', $this->education(['start_year' => 2027]))->assertStatus(422)->assertJsonStructure(['errors' => ['start_year']]);
        $this->api('POST', '/me/educations', $this->education(['end_year' => 2027]))->assertStatus(422)->assertJsonStructure(['errors' => ['end_year']]);
        $this->api('POST', '/me/educations', $this->education(['end_year' => null]))->assertStatus(422)->assertJsonStructure(['errors' => ['end_year']]);
        $this->api('POST', '/me/educations', $this->education(['gpa' => 3.456]))->assertStatus(422)->assertJsonStructure(['errors' => ['gpa']]);
        $this->api('POST', '/me/educations', $this->education(['gpa' => -1]))->assertStatus(422);
        $this->api('POST', '/me/educations', $this->education(['gpa' => 'high']))->assertStatus(422);
        $this->api('POST', '/me/educations', $this->education(['education_level_id' => 999_999]))->assertStatus(422)->assertJsonStructure(['errors' => ['education_level_id']]);
        $this->api('POST', '/me/educations', ['institution' => 'Institut Teknologi Bandung'])->assertStatus(422);

        $this->api('POST', '/me/educations', $this->education(['gpa' => 4, 'start_year' => 1950, 'end_year' => 2026]))->assertCreated()->assertJsonPath('data.gpa', '4.00');
        $this->assertSame(1, DB::table('jobseeker_educations')->count());
    }

    public function test_an_education_change_is_checked_together_with_what_stays(): void
    {
        $id = (int) $this->api('POST', '/me/educations', $this->education())->json('data.id');

        $this->api('PATCH', "/me/educations/{$id}", ['start_year' => 2019])->assertStatus(422)->assertJsonStructure(['errors' => ['end_year']]);
        $this->api('PATCH', "/me/educations/{$id}", ['is_current' => true, 'end_year' => null])->assertOk()->assertJsonPath('data.is_current', true);
        $this->assertSame(2014, (int) DB::table('jobseeker_educations')->where('id', $id)->value('start_year'));
    }

    public function test_at_most_fifteen_education_entries_and_none_of_another_account(): void
    {
        $level = $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]);

        for ($i = 1; $i <= 15; $i++) {
            DB::table('jobseeker_educations')->insert([
                'user_id' => $this->userId, 'education_level_id' => $level, 'institution' => "School {$i}", 'start_year' => 2000, 'end_year' => 2001, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->api('POST', '/me/educations', $this->education())->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:education-limit-reached');

        $other = $this->account('job_seeker', 'budi@example.test');
        $this->api('GET', '/me/educations', token: $this->tokenFor($other))->assertExactJson(['data' => []]);
        $this->api('PATCH', '/me/educations/'.DB::table('jobseeker_educations')->value('id'), ['institution' => 'Hijacked'], $this->tokenFor($other))->assertStatus(404);
    }

    // ----- skills -----

    public function test_the_skills_are_replaced_whole_and_keep_their_order(): void
    {
        $php = $this->skillId('php');
        $sql = $this->referenceId('reference_skills', 'sql', ['kind' => 'hard']);
        $git = $this->referenceId('reference_skills', 'git', ['kind' => 'hard']);

        $this->api('PUT', '/me/skills', ['skill_ids' => [$sql, $php, $git]])->assertOk()->assertJsonPath('data.skills.0.id', $sql)->assertJsonPath('data.skills.0.name', 'sql')
            ->assertJsonPath('data.skills.1.id', $php)->assertJsonPath('data.skills.2.id', $git);

        $this->api('PUT', '/me/skills', ['skill_ids' => [$git]])->assertOk()->assertJsonCount(1, 'data.skills');
        $this->assertSame([$git], DB::table('jobseeker_skills')->where('user_id', $this->userId)->pluck('skill_id')->map(fn ($id) => (int) $id)->all());

        $this->api('PUT', '/me/skills', ['skill_ids' => []])->assertOk()->assertJsonPath('data.skills', []);
        $this->assertSame(0, DB::table('jobseeker_skills')->count());
    }

    public function test_bad_skills_are_refused_and_nothing_changes(): void
    {
        $php = $this->skillId('php');
        $inactive = $this->referenceId('reference_skills', 'cobol', ['kind' => 'hard', 'is_active' => false]);
        $this->api('PUT', '/me/skills', ['skill_ids' => [$php]])->assertOk();

        $this->api('PUT', '/me/skills', ['skill_ids' => [$php, $php]])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-skills')->assertJsonStructure(['errors' => ['skill_ids']]);
        $this->api('PUT', '/me/skills', ['skill_ids' => [$php, 999_999]])->assertStatus(422)->assertJsonStructure(['errors' => ['skill_ids.1']]);
        $this->api('PUT', '/me/skills', ['skill_ids' => [$inactive]])->assertStatus(422)->assertJsonStructure(['errors' => ['skill_ids.0']]);
        $this->api('PUT', '/me/skills', [])->assertStatus(422);
        $this->api('PUT', '/me/skills', ['skill_ids' => ['x']])->assertStatus(422);

        $ids = [];

        for ($i = 1; $i <= 31; $i++) {
            $ids[] = $this->referenceId('reference_skills', "skill-{$i}", ['kind' => 'hard']);
        }

        $this->api('PUT', '/me/skills', ['skill_ids' => $ids])->assertStatus(422)->assertJsonStructure(['errors' => ['skill_ids']]);
        $this->api('PUT', '/me/skills', ['skill_ids' => array_slice($ids, 0, 30)])->assertOk()->assertJsonCount(30, 'data.skills');
    }

    // ----- languages -----

    public function test_the_languages_are_replaced_whole_with_their_proficiency(): void
    {
        $english = $this->referenceId('reference_languages', 'english', ['code' => 'en']);
        $indonesian = $this->referenceId('reference_languages', 'indonesian', ['code' => 'id']);

        $this->api('PUT', '/me/languages', ['languages' => [['language_id' => $indonesian, 'proficiency' => 'native'], ['language_id' => $english, 'proficiency' => 'fluent']]])->assertOk()
            ->assertJsonPath('data.languages.0.id', $indonesian)->assertJsonPath('data.languages.0.proficiency', 'native')->assertJsonPath('data.languages.0.name', 'indonesian')
            ->assertJsonPath('data.languages.1.proficiency', 'fluent');

        $this->api('PUT', '/me/languages', ['languages' => [['language_id' => $english, 'proficiency' => 'basic']]])->assertOk()->assertJsonCount(1, 'data.languages');
        $this->api('PUT', '/me/languages', ['languages' => []])->assertOk()->assertJsonPath('data.languages', []);
    }

    public function test_bad_languages_are_refused(): void
    {
        $english = $this->referenceId('reference_languages', 'english', ['code' => 'en']);
        $retired = $this->referenceId('reference_languages', 'latin', ['code' => 'la', 'is_active' => false]);

        $this->api('PUT', '/me/languages', ['languages' => [['language_id' => $english, 'proficiency' => 'expert']]])->assertStatus(422);
        $this->api('PUT', '/me/languages', ['languages' => [['language_id' => $english]]])->assertStatus(422);
        $this->api('PUT', '/me/languages', ['languages' => [['language_id' => $english, 'proficiency' => 'basic'], ['language_id' => $english, 'proficiency' => 'native']]])->assertStatus(422)
            ->assertJsonPath('type', 'urn:jobportal:error:invalid-languages')->assertJsonStructure(['errors' => ['languages']]);
        $this->api('PUT', '/me/languages', ['languages' => [['language_id' => 999_999, 'proficiency' => 'basic']]])->assertStatus(422)->assertJsonStructure(['errors' => ['languages.0.language_id']]);
        $this->api('PUT', '/me/languages', ['languages' => [['language_id' => $retired, 'proficiency' => 'basic']]])->assertStatus(422);

        $many = [];

        for ($i = 1; $i <= 11; $i++) {
            $many[] = ['language_id' => $this->referenceId('reference_languages', "language-{$i}", ['code' => "x{$i}"]), 'proficiency' => 'basic'];
        }

        $this->api('PUT', '/me/languages', ['languages' => $many])->assertStatus(422)->assertJsonStructure(['errors' => ['languages']]);
        $this->api('PUT', '/me/languages', ['languages' => array_slice($many, 0, 10)])->assertOk()->assertJsonCount(10, 'data.languages');
    }

    // ----- links -----

    public function test_the_links_are_replaced_whole(): void
    {
        $response = $this->api('PUT', '/me/links', ['links' => [
            ['type' => 'linkedin', 'url' => 'https://www.linkedin.com/in/siti'], ['type' => 'github', 'url' => 'https://github.com/siti'],
            ['type' => 'portfolio', 'url' => 'https://siti.example.test/work'], ['type' => 'other', 'url' => 'https://blog.example.test'],
        ]])->assertOk();

        $this->assertSame(['linkedin', 'github', 'portfolio', 'other'], array_column($response->json('data.links'), 'type'));
        $response->assertJsonPath('data.links.2.url', 'https://siti.example.test/work');

        $this->api('PUT', '/me/links', ['links' => [['type' => 'x', 'url' => 'https://twitter.com/siti']]])->assertOk()->assertJsonCount(1, 'data.links');
        $this->assertSame(1, DB::table('jobseeker_links')->count());
        $this->api('PUT', '/me/links', ['links' => []])->assertOk()->assertJsonPath('data.links', []);
    }

    public function test_unsafe_or_wrong_links_are_refused_with_their_position_and_nothing_changes(): void
    {
        $this->api('PUT', '/me/links', ['links' => [['type' => 'website', 'url' => 'https://siti.example.test']]])->assertOk();

        $response = $this->api('PUT', '/me/links', ['links' => [
            ['type' => 'linkedin', 'url' => 'https://www.linkedin.com/in/siti'], ['type' => 'github', 'url' => 'https://evil.example.test/siti'], ['type' => 'website', 'url' => 'https://bit.ly/xyz'],
            ['type' => 'linkedin', 'url' => 'https://linkedin.com/in/other'], ['type' => 'portfolio', 'url' => 'http://siti.example.test'], ['type' => 'other', 'url' => 'https://10.0.0.1/cv'],
        ]])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-links');

        $this->assertSame(['links.1.url', 'links.2.url', 'links.3.type', 'links.4.url', 'links.5.url'], array_keys($response->json('errors')));
        $this->api('PUT', '/me/links', ['links' => [['type' => 'blog', 'url' => 'https://siti.example.test']]])->assertStatus(422);
        $this->api('PUT', '/me/links', ['links' => [['type' => 'website']]])->assertStatus(422);
        $this->api('PUT', '/me/links', [])->assertStatus(422);

        $this->assertSame(['https://siti.example.test'], DB::table('jobseeker_links')->pluck('url')->all());
    }

    // ----- the profile shows every list -----

    public function test_the_profile_shows_every_list_and_counts_the_history_for_completeness(): void
    {
        $php = $this->skillId('php');
        $english = $this->referenceId('reference_languages', 'english', ['code' => 'en']);

        $this->api('GET', '/me/profile')->assertOk()->assertJsonPath('data.experiences', [])->assertJsonPath('data.educations', [])->assertJsonPath('data.skills', [])
            ->assertJsonPath('data.languages', [])->assertJsonPath('data.links', [])->assertJsonPath('data.completeness.missing', ['phone', 'location', 'history', 'resume']);

        $this->api('POST', '/me/experiences', $this->experience())->assertCreated();
        $this->api('POST', '/me/educations', $this->education())->assertCreated();
        $this->api('PUT', '/me/skills', ['skill_ids' => [$php]])->assertOk();
        $this->api('PUT', '/me/languages', ['languages' => [['language_id' => $english, 'proficiency' => 'fluent']]])->assertOk();
        $this->api('PUT', '/me/links', ['links' => [['type' => 'github', 'url' => 'https://github.com/siti']]])->assertOk();

        $profile = $this->api('GET', '/me/profile')->assertOk();
        $profile->assertJsonCount(1, 'data.experiences')->assertJsonCount(1, 'data.educations')->assertJsonPath('data.skills.0.id', $php)
            ->assertJsonPath('data.languages.0.proficiency', 'fluent')->assertJsonPath('data.links.0.type', 'github')->assertJsonPath('data.experiences.0.start_date', '2022-03')
            ->assertJsonPath('data.completeness.missing', ['phone', 'location', 'resume']);

        $this->api('PATCH', '/me/profile', ['headline' => 'Developer'])->assertOk()->assertJsonCount(1, 'data.experiences')->assertJsonCount(1, 'data.skills');
    }

    public function test_the_lists_of_one_account_are_not_shown_to_another(): void
    {
        $other = $this->tokenFor($this->account('job_seeker', 'budi@example.test'));
        $this->api('POST', '/me/experiences', $this->experience())->assertCreated();
        $this->api('PUT', '/me/skills', ['skill_ids' => [$this->skillId('php')]])->assertOk();

        $this->api('GET', '/me/profile', token: $other)->assertJsonPath('data.experiences', [])->assertJsonPath('data.skills', []);
        $this->api('PUT', '/me/skills', ['skill_ids' => []], $other)->assertOk();
        $this->assertSame(1, DB::table('jobseeker_skills')->count());
    }

    // ----- a profile migrated over the limits -----

    public function test_a_profile_over_the_skill_limit_can_keep_or_shorten_its_list_but_not_lengthen_it(): void
    {
        $ids = [];

        for ($i = 1; $i <= 36; $i++) {
            $ids[] = $this->referenceId('reference_skills', "skill-{$i}", ['kind' => 'hard']);
            DB::table('jobseeker_skills')->insert(['user_id' => $this->userId, 'skill_id' => $ids[$i - 1], 'position' => $i]);
        }

        $this->api('PUT', '/me/skills', ['skill_ids' => array_reverse($ids)])->assertOk()->assertJsonCount(36, 'data.skills');
        $this->api('PUT', '/me/skills', ['skill_ids' => array_slice($ids, 0, 33)])->assertOk()->assertJsonCount(33, 'data.skills');
        $this->api('PUT', '/me/skills', ['skill_ids' => [...array_slice($ids, 0, 32), $ids[35]]])->assertOk()->assertJsonCount(33, 'data.skills');

        $this->api('PUT', '/me/skills', ['skill_ids' => $ids])->assertStatus(422)->assertJsonStructure(['errors' => ['skill_ids']]);
    }

    public function test_a_profile_over_the_language_limit_can_keep_its_list_but_not_lengthen_it(): void
    {
        $ids = [];

        for ($i = 1; $i <= 12; $i++) {
            $ids[] = $this->referenceId('reference_languages', "language-{$i}", ['code' => "l{$i}"]);
            DB::table('jobseeker_languages')->insert(['user_id' => $this->userId, 'language_id' => $ids[$i - 1], 'proficiency' => 'conversational']);
        }

        $entries = fn (array $languageIds): array => array_map(fn (int $id): array => ['language_id' => $id, 'proficiency' => 'fluent'], $languageIds);

        $this->api('PUT', '/me/languages', ['languages' => $entries($ids)])->assertOk();
        $this->api('PUT', '/me/languages', ['languages' => $entries(array_slice($ids, 0, 11))])->assertOk();
        $this->api('PUT', '/me/languages', ['languages' => $entries([...array_slice($ids, 0, 11), $ids[11], $this->referenceId('reference_languages', 'language-13', ['code' => 'l13'])])])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['languages']]);
    }

    public function test_a_stored_link_that_breaks_the_rules_stays_but_a_new_one_is_still_checked(): void
    {
        DB::table('jobseeker_links')->insert([
            ['user_id' => $this->userId, 'type' => 'website', 'url' => 'http://old.example.test'],
            ['user_id' => $this->userId, 'type' => 'other', 'url' => 'https://bit.ly/abc'],
        ]);
        $kept = [['type' => 'website', 'url' => 'http://old.example.test'], ['type' => 'other', 'url' => 'https://bit.ly/abc']];

        $this->api('PUT', '/me/links', ['links' => $kept])->assertOk();
        $this->api('PUT', '/me/links', ['links' => [...$kept, ['type' => 'other', 'url' => 'http://new.example.test']]])->assertStatus(422)->assertJsonStructure(['errors' => ['links.2.url']]);
        $this->api('PUT', '/me/links', ['links' => [['type' => 'website', 'url' => 'http://other.example.test']]])->assertStatus(422)->assertJsonStructure(['errors' => ['links.0.url']]);
    }

    public function test_an_experience_over_the_text_limits_can_change_another_field_but_a_changed_text_is_checked(): void
    {
        $long = str_repeat('a', 2500);
        $id = (int) DB::table('jobseeker_experiences')->insertGetId([
            'user_id' => $this->userId, 'job_title' => 'Backend Developer', 'company_name' => str_repeat('c', 120), 'start_date' => '2022-03-01', 'end_date' => '2024-06-01',
            'is_current' => false, 'description' => $long, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api('PATCH', "/me/experiences/{$id}", ['job_title' => 'Senior Backend Developer', 'description' => $long])->assertOk();
        $this->api('PATCH', "/me/experiences/{$id}", ['description' => $long.'b'])->assertStatus(422)->assertJsonStructure(['errors' => ['description']]);
        $this->api('PATCH', "/me/experiences/{$id}", ['company_name' => str_repeat('d', 121)])->assertStatus(422)->assertJsonStructure(['errors' => ['company_name']]);
    }

    public function test_an_education_with_a_gpa_over_four_can_change_another_field_but_a_changed_gpa_is_checked(): void
    {
        $id = (int) DB::table('jobseeker_educations')->insertGetId([
            'user_id' => $this->userId, 'education_level_id' => $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]), 'institution' => 'Institut Teknologi Bandung',
            'start_year' => 2014, 'end_year' => 2018, 'gpa' => '85.00', 'description' => str_repeat('n', 1200), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api('PATCH', "/me/educations/{$id}", ['institution' => 'ITB', 'gpa' => '85', 'description' => str_repeat('n', 1200)])->assertOk();
        $this->api('PATCH', "/me/educations/{$id}", ['gpa' => '86'])->assertStatus(422)->assertJsonStructure(['errors' => ['gpa']]);
        $this->api('PATCH', "/me/educations/{$id}", ['gpa' => '3.50'])->assertOk()->assertJsonPath('data.gpa', '3.50');
        $this->api('PATCH', "/me/educations/{$id}", ['description' => str_repeat('n', 1201)])->assertStatus(422)->assertJsonStructure(['errors' => ['description']]);
    }
}
