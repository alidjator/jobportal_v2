<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class CompanyJobDraftTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    // ----- creating -----

    public function test_a_draft_needs_only_a_title_and_is_assigned_to_its_creator(): void
    {
        $response = $this->api('POST', '/company/jobs', ['title' => 'Backend Developer'], $this->recruiterToken)->assertCreated();

        $id = $response->json('data.id');
        $this->assertSame('backend-developer-'.$id, $response->json('data.slug'));
        $this->assertSame(['draft', 'Backend Developer', true, null, null], [
            $response->json('data.status'), $response->json('data.title'), $response->json('data.salary_visible'),
            $response->json('data.description'), $response->json('data.deadline'),
        ]);
        $this->assertSame([], [...$response->json('data.skills'), ...$response->json('data.tags'), ...$response->json('data.benefits')]);
        $this->assertSame([['user_id' => $this->recruiterId, 'name' => 'Rina Rekruter', 'role' => 'member', 'job_role' => 'recruiter']], $response->json('data.assignees'));
        $this->assertSame($this->recruiterId, $response->json('data.created_by_user_id'));
        $this->assertSame($this->companyId, (int) $this->jobRow($id)->company_id);
        $this->assertSame([$this->recruiterId], $this->assigneesOf($id));
        $this->assertArrayNotHasKey('status_reason', $response->json('data'));
    }

    public function test_a_company_admin_can_create_a_draft_too(): void
    {
        $this->api('POST', '/company/jobs', ['title' => 'Data Analyst'])->assertCreated();

        $this->assertSame(1, DB::table('job_jobs')->count());
    }

    public function test_a_draft_with_every_field_is_saved_and_shown_with_names(): void
    {
        $body = $this->fullJobBody();

        $response = $this->api('POST', '/company/jobs', $body, headers: ['Accept-Language' => 'en'])->assertCreated();
        $data = $response->json('data');

        foreach (['category_id', 'role_id', 'job_type_id', 'job_level_id', 'work_time_id', 'experience_level_id', 'education_level_id', 'vacancies', 'deadline',
            'salary_min', 'salary_max', 'salary_period_id', 'salary_visible', 'work_mode', 'province_id', 'city_id', 'subdistrict_id', 'village_id',
            'postal_code_id', 'address_line', 'description'] as $field) {
            $this->assertSame($body[$field], $data[$field], $field);
        }

        $this->assertSame([['id' => $body['skill_ids'][0], 'name' => 'php'], ['id' => $body['skill_ids'][1], 'name' => 'laravel']], $data['skills']);
        $this->assertSame([['id' => $body['benefit_ids'][0], 'name' => 'insurance']], $data['benefits']);
        $this->assertSame(['php', 'laravel', 'c++'], $data['tags']);
        $this->assertSame('draft', $data['status']);
    }

    public function test_tags_are_normalised_and_registered_once(): void
    {
        $first = $this->api('POST', '/company/jobs', ['title' => 'One', 'tags' => ['PHP', '  Laravel  ', 'c++', 'C#', 'php']])->assertCreated();
        $second = $this->api('POST', '/company/jobs', ['title' => 'Two', 'tags' => ['laravel', 'php']])->assertCreated();

        $this->assertSame(['php', 'laravel', 'c++', 'c#'], $first->json('data.tags'));
        $this->assertSame(['laravel', 'php'], $second->json('data.tags'));
        $this->assertSame(
            ['c-plus-plus', 'c-sharp', 'laravel', 'php'],
            DB::table('reference_tags')->orderBy('slug')->pluck('slug')->all(),
        );
        $this->assertSame(6, DB::table('job_job_tags')->count());
    }

    public function test_the_title_is_required_and_has_a_length_range(): void
    {
        $this->api('POST', '/company/jobs', [])->assertStatus(422)->assertJsonValidationErrors(['title']);
        $this->api('POST', '/company/jobs', ['title' => 'ab'])->assertStatus(422)->assertJsonValidationErrors(['title']);
        $this->api('POST', '/company/jobs', ['title' => str_repeat('a', 151)])->assertStatus(422)->assertJsonValidationErrors(['title']);

        $this->assertSame(0, DB::table('job_jobs')->count());
    }

    public function test_the_shape_of_every_field_is_checked(): void
    {
        $bad = [
            'description' => str_repeat('a', 20001), 'category_id' => 0, 'vacancies' => 0, 'deadline' => '31-12-2026', 'salary_min' => -1,
            'salary_visible' => 'maybe', 'work_mode' => 'office', 'skill_ids' => range(1, 16), 'benefit_ids' => range(1, 16), 'tags' => 'php',
            'address_line' => str_repeat('a', 256),
        ];

        $response = $this->api('POST', '/company/jobs', ['title' => 'Backend', ...$bad])->assertStatus(422);

        $response->assertJsonValidationErrors(array_keys($bad));
        $this->assertSame(0, DB::table('job_jobs')->count());
    }

    // ----- validity (checked against the masters) -----

    public function test_unknown_or_inactive_references_are_refused_per_field(): void
    {
        $body = $this->fullJobBody();
        DB::table('reference_job_categories')->where('id', $body['category_id'])->update(['is_active' => false]);
        $inactiveSkill = $this->skillId('inactive');
        DB::table('reference_skills')->where('id', $inactiveSkill)->update(['is_active' => false]);

        $response = $this->api('POST', '/company/jobs', [
            ...$body, 'role_id' => 999999, 'skill_ids' => [$inactiveSkill, 999998], 'benefit_ids' => [999997],
        ])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-job');

        $errors = $response->json('errors');
        $this->assertSame(['category_id', 'role_id', 'skill_ids', 'benefit_ids'], array_keys($errors));
        $this->assertCount(2, $errors['skill_ids']);
        $this->assertSame(0, DB::table('job_jobs')->count());
    }

    public function test_a_benefit_that_is_not_approved_cannot_be_chosen(): void
    {
        $pending = $this->referenceId('reference_benefits', 'gym', ['status' => 'pending', 'proposed_by_company_id' => $this->otherCompanyId]);

        $this->api('POST', '/company/jobs', ['title' => 'Backend', 'benefit_ids' => [$pending]])
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-job')->assertJsonStructure(['errors' => ['benefit_ids']]);
    }

    public function test_the_location_must_be_one_matching_chain(): void
    {
        $chain = $this->locationChain();
        $otherProvince = DB::table('reference_provinces')->insertGetId(['bps_code' => '31', 'name' => 'DKI JAKARTA']);

        $this->api('POST', '/company/jobs', ['title' => 'Backend', ...$chain])->assertCreated();
        $this->api('POST', '/company/jobs', ['title' => 'Backend', ...$chain, 'province_id' => $otherProvince])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['location']]);
        $this->api('POST', '/company/jobs', ['title' => 'Backend', 'city_id' => $chain['city_id']])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['location']]);
    }

    public function test_the_salary_must_be_consistent(): void
    {
        $period = $this->referenceId('reference_salary_periods', 'monthly');

        $this->api('POST', '/company/jobs', ['title' => 'Backend', 'salary_min' => 1000])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['salary_period_id']]);
        $this->api('POST', '/company/jobs', ['title' => 'Backend', 'salary_min' => 2000, 'salary_max' => 1000, 'salary_period_id' => $period])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['salary_min']]);

        $this->api('POST', '/company/jobs', ['title' => 'Backend', 'salary_max' => 2000, 'salary_period_id' => $period])->assertCreated();
        $this->api('POST', '/company/jobs', ['title' => 'Backend', 'salary_min' => 2000, 'salary_period_id' => $period])->assertCreated();
        $this->api('POST', '/company/jobs', ['title' => 'Backend', 'salary_min' => 2000, 'salary_max' => 2000, 'salary_period_id' => $period])->assertCreated();
    }

    public function test_bad_tags_are_refused_together_with_other_problems(): void
    {
        foreach ([['a'], [str_repeat('a', 31)], ['@@@'], ['++'], ['php!'], range(1, 11)] as $tags) {
            $response = $this->api('POST', '/company/jobs', ['title' => 'Backend', 'tags' => array_map('strval', $tags)])->assertStatus(422);
            $this->assertArrayHasKey('tags', $response->json('errors'), json_encode($tags));
        }

        $response = $this->api('POST', '/company/jobs', ['title' => 'Backend', 'tags' => ['a'], 'role_id' => 999999])->assertStatus(422);
        $this->assertSame(['tags', 'role_id'], array_keys($response->json('errors')));
        $this->assertSame(0, DB::table('job_jobs')->count());
        $this->assertSame(0, DB::table('reference_tags')->count());
    }

    // ----- changing -----

    public function test_a_patch_changes_only_what_is_sent_and_a_null_clears_an_optional_field(): void
    {
        $id = $this->api('POST', '/company/jobs', $this->fullJobBody())->json('data.id');

        $response = $this->api('PATCH', "/company/jobs/{$id}", ['vacancies' => 5, 'address_line' => null, 'description' => 'Short.'])->assertOk();

        $this->assertSame([5, null, 'Short.', 'Senior Backend Developer', 'hybrid'], [
            $response->json('data.vacancies'), $response->json('data.address_line'), $response->json('data.description'),
            $response->json('data.title'), $response->json('data.work_mode'),
        ]);
        $this->assertCount(2, $response->json('data.skills'));
        $this->assertSame(['php', 'laravel', 'c++'], $response->json('data.tags'));
    }

    public function test_the_lists_are_replaced_as_a_whole_when_sent_and_kept_when_not(): void
    {
        $id = $this->api('POST', '/company/jobs', $this->fullJobBody())->json('data.id');
        $newSkill = $this->skillId('go');

        $response = $this->api('PATCH', "/company/jobs/{$id}", ['skill_ids' => [$newSkill], 'tags' => ['go'], 'benefit_ids' => []])->assertOk();

        $this->assertSame([['id' => $newSkill, 'name' => 'go']], $response->json('data.skills'));
        $this->assertSame(['go'], $response->json('data.tags'));
        $this->assertSame([], $response->json('data.benefits'));
        $this->assertSame(1, DB::table('job_job_skills')->where('job_id', $id)->count());
        $this->assertSame(0, DB::table('job_job_benefits')->where('job_id', $id)->count());
    }

    public function test_a_new_title_gives_a_new_slug_and_the_same_title_keeps_it(): void
    {
        $id = $this->api('POST', '/company/jobs', ['title' => 'Backend Developer'])->json('data.id');

        $this->assertSame("data-engineer-{$id}", $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'Data Engineer'])->json('data.slug'));
        $this->assertSame("data-engineer-{$id}", $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'Data Engineer'])->json('data.slug'));
    }

    public function test_an_empty_patch_changes_nothing_and_the_title_cannot_be_cleared(): void
    {
        $id = $this->api('POST', '/company/jobs', ['title' => 'Backend Developer'])->json('data.id');

        $this->api('PATCH', "/company/jobs/{$id}", [])->assertOk()->assertJsonPath('data.title', 'Backend Developer');
        $this->api('PATCH', "/company/jobs/{$id}", ['title' => null])->assertStatus(422)->assertJsonValidationErrors(['title']);
    }

    public function test_only_what_changed_is_checked_against_the_masters(): void
    {
        $body = $this->fullJobBody();
        $id = $this->api('POST', '/company/jobs', $body)->json('data.id');
        DB::table('reference_job_categories')->where('id', $body['category_id'])->update(['is_active' => false]);
        DB::table('reference_skills')->where('id', $body['skill_ids'][0])->update(['is_active' => false]);

        $this->api('PATCH', "/company/jobs/{$id}", ['vacancies' => 9, 'skill_ids' => $body['skill_ids']])->assertOk()->assertJsonPath('data.vacancies', 9);
        $this->api('PATCH', "/company/jobs/{$id}", ['category_id' => $body['category_id'], 'job_type_id' => 999999])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['job_type_id']]);
    }

    public function test_a_location_change_is_checked_together_with_the_levels_that_stay(): void
    {
        $body = $this->fullJobBody();
        $id = $this->api('POST', '/company/jobs', $body)->json('data.id');
        $otherCity = DB::table('reference_cities')->insertGetId([
            'province_id' => DB::table('reference_provinces')->insertGetId(['bps_code' => '31', 'name' => 'DKI JAKARTA']),
            'bps_code' => '3171', 'name' => 'JAKARTA PUSAT', 'type' => 'city',
        ]);

        $this->api('PATCH', "/company/jobs/{$id}", ['city_id' => $otherCity])->assertStatus(422)->assertJsonStructure(['errors' => ['location']]);
        $this->api('PATCH', "/company/jobs/{$id}", ['subdistrict_id' => null, 'village_id' => null, 'postal_code_id' => null])->assertOk();
    }

    public function test_a_job_waiting_for_review_or_taken_down_cannot_be_edited(): void
    {
        foreach (['pending_review', 'taken_down'] as $status) {
            $id = $this->insertJob("Job {$status}", $status);

            $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'Changed'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-editable');
            $this->assertSame("Job {$status}", $this->jobRow($id)->title);
        }

        $rejected = $this->insertJob('Rejected one', 'rejected', attributes: ['status_reason' => 'Not clear.']);
        $response = $this->api('PATCH', "/company/jobs/{$rejected}", ['title' => 'Better title'])->assertOk();

        $this->assertSame(['rejected', 'Not clear.'], [$response->json('data.status'), $response->json('data.status_reason')]);
    }

    public function test_the_status_is_checked_before_the_content(): void
    {
        $id = $this->insertJob('Waiting job', 'pending_review');

        $this->api('PATCH', "/company/jobs/{$id}", ['role_id' => 999999])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-editable');
    }

    // ----- who may touch a job -----

    public function test_a_recruiter_cannot_see_or_change_a_job_that_is_not_assigned_to_them(): void
    {
        $id = $this->insertJob('Admin only', 'draft', [$this->adminId]);

        $this->api('GET', "/company/jobs/{$id}", token: $this->recruiterToken)->assertNotFound();
        $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'Mine now'], $this->recruiterToken)->assertNotFound();
        $this->api('DELETE', "/company/jobs/{$id}", token: $this->recruiterToken)->assertNotFound();

        $this->assertSame('Admin only', $this->jobRow($id)->title);
    }

    public function test_a_recruiter_works_on_a_job_assigned_to_them_and_a_company_admin_on_any(): void
    {
        $id = $this->insertJob('Shared', 'draft', [$this->recruiterId]);

        $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'By recruiter'], $this->recruiterToken)->assertOk();
        $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'By admin'])->assertOk()->assertJsonPath('data.title', 'By admin');
        $this->api('GET', "/company/jobs/{$id}", token: $this->secondRecruiterToken)->assertNotFound();
    }

    public function test_a_job_of_another_company_is_a_404_even_for_its_admin(): void
    {
        $id = $this->insertJob('Theirs', 'draft', [$this->otherAdminId], $this->otherCompanyId);

        $this->api('GET', "/company/jobs/{$id}")->assertNotFound();
        $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'Mine'])->assertNotFound();
        $this->api('DELETE', "/company/jobs/{$id}")->assertNotFound();
        $this->api('GET', '/company/jobs/999999')->assertNotFound();
    }

    // ----- deleting -----

    public function test_a_draft_is_deleted_with_its_lists_and_assignments_but_the_tags_stay(): void
    {
        $id = $this->api('POST', '/company/jobs', $this->fullJobBody())->json('data.id');

        $this->api('DELETE', "/company/jobs/{$id}")->assertNoContent();

        $this->assertNull($this->jobRow($id));
        foreach (['job_job_skills', 'job_job_tags', 'job_job_benefits', 'job_assignments'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame(3, DB::table('reference_tags')->count());
        $this->api('GET', "/company/jobs/{$id}")->assertNotFound();
    }

    public function test_a_job_that_is_not_a_draft_is_not_deleted(): void
    {
        foreach (['pending_review', 'active', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $id = $this->insertJob("Job {$status}", $status);

            $this->api('DELETE', "/company/jobs/{$id}")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-draft');
            $this->assertNotNull($this->jobRow($id), $status);
        }
    }

    // ----- no audit for drafts -----

    public function test_creating_changing_and_deleting_a_draft_leave_no_audit_entry(): void
    {
        $id = $this->api('POST', '/company/jobs', ['title' => 'Backend'])->json('data.id');
        $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'Backend Two'])->assertOk();
        $this->api('DELETE', "/company/jobs/{$id}")->assertNoContent();

        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'like', 'job.%')->count());
    }
}
