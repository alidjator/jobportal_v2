<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

/**
 * `docs/design/phase-8-job-posting.md` sections 1 to 3 and `phase-8-rejections.md` section 3: the summary, the hidden vacancies, the salary note and the waiting period
 * of a job, and the preview of a job as the public will see it.
 */
final class JobPostingFieldsTest extends FeatureTestCase
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

    /** @param  array<string, mixed>  $override */
    private function draft(string $title = 'Senior Backend Developer', array $override = [], ?string $token = null): int
    {
        return (int) $this->api('POST', '/company/jobs', [...$this->fullJobBody($title), ...$override], $token)->assertCreated()->json('data.id');
    }

    private function publicJob(int $id): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->getJson("/api/v1/jobs/{$id}");
    }

    private function summary(): string
    {
        return 'Build the APIs of the job portal with a small team.';
    }

    // ----- the fields -----

    public function test_a_new_job_starts_with_no_summary_no_note_a_visible_number_and_no_waiting_period(): void
    {
        $data = $this->api('POST', '/company/jobs', ['title' => 'Backend Developer'])->assertCreated()->json('data');

        $this->assertSame([null, true, null, null], [$data['summary'], $data['vacancies_visible'], $data['salary_note'], $data['reapply_after_days']]);
        $this->assertSame([], $data['location_ids']);
        $this->assertSame([], $data['locations']);
    }

    public function test_the_fields_are_saved_changed_and_cleared(): void
    {
        $id = $this->draft(override: ['summary' => '  '.$this->summary().'  ', 'vacancies_visible' => false, 'salary_note' => ' Gaji pokok + insentif bulanan ', 'reapply_after_days' => 90]);

        $data = $this->api('GET', "/company/jobs/{$id}")->assertOk()->json('data');
        $this->assertSame([$this->summary(), false, 'Gaji pokok + insentif bulanan', 90, 3], [$data['summary'], $data['vacancies_visible'], $data['salary_note'], $data['reapply_after_days'], $data['vacancies']]);

        $this->api('PATCH', "/company/jobs/{$id}", ['summary' => null, 'vacancies_visible' => true, 'salary_note' => '', 'reapply_after_days' => null])->assertOk()
            ->assertJsonPath('data.summary', null)->assertJsonPath('data.vacancies_visible', true)->assertJsonPath('data.salary_note', null)->assertJsonPath('data.reapply_after_days', null);
    }

    public function test_the_summary_is_one_line_of_20_to_150_characters_without_a_link(): void
    {
        $id = $this->draft();

        foreach (['too short', str_repeat('a', 151), "Two lines\nof text that is long enough"] as $bad) {
            $this->api('PATCH', "/company/jobs/{$id}", ['summary' => $bad])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-summary')->assertJsonStructure(['errors' => ['summary']]);
        }

        $this->api('PATCH', "/company/jobs/{$id}", ['summary' => 'Apply at https://example.com/jobs today please'])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed')->assertJsonStructure(['errors' => ['summary']]);
        $this->api('PATCH', "/company/jobs/{$id}", ['summary' => str_repeat('a', 20)])->assertOk();
        $this->api('PATCH', "/company/jobs/{$id}", ['summary' => str_repeat('a', 150)])->assertOk();
        $this->assertNull($this->jobRow($id)->salary_note);
    }

    public function test_the_salary_note_is_one_line_of_at_most_150_characters_without_a_link(): void
    {
        $id = $this->draft();

        $this->api('PATCH', "/company/jobs/{$id}", ['salary_note' => str_repeat('a', 151)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-salary-note');
        $this->api('PATCH', "/company/jobs/{$id}", ['salary_note' => "Kompetitif\nplus bonus"])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-salary-note');
        $this->api('PATCH', "/company/jobs/{$id}", ['salary_note' => 'Lihat www.example.com untuk gaji'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
        $this->api('PATCH', "/company/jobs/{$id}", ['salary_note' => 'Kompetitif'])->assertOk()->assertJsonPath('data.salary_note', 'Kompetitif');
    }

    public function test_the_waiting_period_is_30_to_365_days_or_null(): void
    {
        $id = $this->draft();

        foreach ([0, 29, 366, 'soon'] as $bad) {
            $this->api('PATCH', "/company/jobs/{$id}", ['reapply_after_days' => $bad])->assertStatus(422)->assertJsonValidationErrors(['reapply_after_days']);
        }

        $this->api('PATCH', "/company/jobs/{$id}", ['reapply_after_days' => 30])->assertOk()->assertJsonPath('data.reapply_after_days', 30);
        $this->api('PATCH', "/company/jobs/{$id}", ['reapply_after_days' => 365])->assertOk()->assertJsonPath('data.reapply_after_days', 365);
    }

    public function test_a_clone_keeps_the_fields(): void
    {
        $id = $this->draft(override: ['summary' => $this->summary(), 'vacancies_visible' => false, 'salary_note' => 'Kompetitif', 'reapply_after_days' => 60]);

        $copy = (int) $this->api('POST', "/company/jobs/{$id}/clone")->assertCreated()->json('data.id');

        $row = $this->jobRow($copy);
        $this->assertSame([$this->summary(), false, 'Kompetitif', 60], [$row->summary, (bool) $row->vacancies_visible, $row->salary_note, (int) $row->reapply_after_days]);
    }

    // ----- what the public sees -----

    public function test_the_public_page_and_the_search_row_show_the_summary_and_the_note_even_when_the_salary_is_hidden(): void
    {
        $id = $this->draft(override: ['summary' => $this->summary(), 'salary_note' => 'Kompetitif', 'salary_visible' => false]);
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();

        $page = $this->publicJob($id)->assertOk()->json('data');
        $this->assertSame([$this->summary(), 'Kompetitif', null, 3], [$page['summary'], $page['salary_note'], $page['salary'], $page['vacancies']]);

        $row = $this->getJson('/api/v1/jobs')->assertOk()->json('data.0');
        $this->assertSame([$this->summary(), 'Kompetitif', null], [$row['summary'], $row['salary_note'], $row['salary']]);
    }

    public function test_hidden_vacancies_are_null_for_the_public_and_shown_to_the_company(): void
    {
        $id = $this->draft(override: ['vacancies_visible' => false]);
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();

        $this->publicJob($id)->assertOk()->assertJsonPath('data.vacancies', null);
        $this->api('GET', "/company/jobs/{$id}")->assertOk()->assertJsonPath('data.vacancies', 3);
    }

    // ----- the preview -----

    public function test_a_draft_is_previewed_as_the_public_page_with_what_still_blocks_publishing(): void
    {
        $id = $this->draft('Draft job', ['description' => 'Short', 'skill_ids' => [], 'summary' => $this->summary()]);

        $response = $this->api('GET', "/company/jobs/{$id}/preview", headers: ['Accept-Language' => 'en'])->assertOk();

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame(['Draft job', $this->summary(), 'Short', 3], [$response->json('data.title'), $response->json('data.summary'), $response->json('data.description'), $response->json('data.vacancies')]);
        $this->assertSame(['id', 'name', 'slug', 'logo_url', 'verified'], array_keys($response->json('data.company')));
        $this->assertSame('engineering', $response->json('data.category.name'));
        $this->assertFalse($response->json('meta.publish_check.ready'));
        $this->assertEqualsCanonicalizing(['description', 'skill_ids'], $response->json('meta.publish_check.missing'));
        // The page itself is not live.
        $this->publicJob($id)->assertNotFound();
    }

    public function test_a_complete_job_previews_as_ready_in_every_status_and_nothing_is_counted(): void
    {
        $id = $this->draft();

        $this->api('GET', "/company/jobs/{$id}/preview")->assertOk()->assertJsonPath('meta.publish_check', ['ready' => true, 'missing' => []]);

        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();
        $this->api('POST', "/company/jobs/{$id}/close")->assertOk();
        $this->api('GET', "/company/jobs/{$id}/preview")->assertOk()->assertJsonPath('data.title', 'Senior Backend Developer');
        $this->assertSame(0, DB::table('job_views')->count());
        $this->assertSame(0, DB::table('job_view_daily')->count());
    }

    public function test_the_preview_shows_hidden_vacancies_as_the_public_will_see_them(): void
    {
        $id = $this->draft(override: ['vacancies_visible' => false, 'salary_visible' => false]);

        $this->api('GET', "/company/jobs/{$id}/preview")->assertOk()->assertJsonPath('data.vacancies', null)->assertJsonPath('data.salary', null);
    }

    public function test_a_company_that_the_public_cannot_see_still_previews_its_jobs(): void
    {
        $id = $this->draft();
        DB::table('company_companies')->where('id', $this->companyId)->update(['is_visible' => false]);

        $this->api('GET', "/company/jobs/{$id}/preview")->assertOk()->assertJsonPath('data.company.name', 'PT-MAJU')->assertJsonPath('data.company.logo_url', null);
    }

    public function test_only_a_member_who_may_see_the_job_previews_it(): void
    {
        $id = $this->draft(token: $this->recruiterToken);

        $this->api('GET', "/company/jobs/{$id}/preview", token: $this->recruiterToken)->assertOk();
        $this->api('GET', "/company/jobs/{$id}/preview", token: $this->secondRecruiterToken)->assertNotFound();
        $this->api('GET', "/company/jobs/{$id}/preview", token: $this->otherAdminToken)->assertNotFound();
        $this->api('GET', '/company/jobs/999999/preview')->assertNotFound();
    }

    public function test_the_preview_needs_a_signed_in_user(): void
    {
        $id = $this->draft();
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/company/jobs/{$id}/preview")->assertUnauthorized();
    }
}
