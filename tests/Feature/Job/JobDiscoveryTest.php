<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Job\Infrastructure\Persistence\JobSearchVectors;
use Src\JobAlert\Domain\AlertCriteria;
use Src\Shared\Application\Job\JobMatcher;
use Src\Shared\Application\References\TagRegistry;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-job-discovery.md` sections 1 to 3: relevance order, the posting-date filter and similar jobs. */
final class JobDiscoveryTest extends FeatureTestCase
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

    private function fetch(string $uri): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->getJson("/api/v1/{$uri}");
    }

    /** @return list<string> */
    private function titles(string $query): array
    {
        return array_column($this->fetch("jobs?{$query}")->assertOk()->json('data'), 'title');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $tags
     */
    private function live(string $title, array $attributes = [], array $tags = [], ?int $companyId = null): int
    {
        $id = $this->insertJob($title, 'active', [$this->adminId], $companyId, ['deadline' => '2026-12-31', 'published_at' => '2026-10-01 08:00:00', ...$attributes]);

        foreach ($this->app->make(TagRegistry::class)->idsFor($tags) as $tagId) {
            DB::table('job_job_tags')->insert(['job_id' => $id, 'tag_id' => $tagId]);
        }

        $this->app->make(JobSearchVectors::class)->refresh($id);

        return $id;
    }

    // ----- relevance -----

    public function test_a_keyword_ranks_the_title_above_a_tag_above_the_description_and_says_so(): void
    {
        $this->live('Waiter', ['description' => 'Serve guests and talk about laravel meetups.', 'published_at' => '2026-10-04 08:00:00']);
        $this->live('Cook', tags: ['laravel'], attributes: ['published_at' => '2026-10-03 08:00:00']);
        $this->live('Laravel Developer', ['published_at' => '2026-10-02 08:00:00']);

        $response = $this->fetch('jobs?filter[q]=laravel')->assertOk()->assertJsonPath('meta.sort', 'relevance');

        $this->assertSame(['Laravel Developer', 'Cook', 'Waiter'], array_column($response->json('data'), 'title'));
        $this->assertSame(['Waiter', 'Cook', 'Laravel Developer'], $this->titles('filter[q]=laravel&sort=-published_at'));
        $this->fetch('jobs?filter[q]=laravel&sort=-published_at')->assertJsonPath('meta.sort', '-published_at');
    }

    public function test_ties_in_relevance_go_to_the_newest_and_a_missing_keyword_falls_back_to_the_newest(): void
    {
        $this->live('Backend Old', ['published_at' => '2026-10-01 08:00:00']);
        $this->live('Backend New', ['published_at' => '2026-10-04 08:00:00']);

        $this->assertSame(['Backend New', 'Backend Old'], $this->titles('filter[q]=backend'));
        $this->fetch('jobs?sort=relevance')->assertOk()->assertJsonPath('meta.sort', '-published_at');
        $this->fetch('jobs')->assertOk()->assertJsonPath('meta.sort', '-published_at');
    }

    public function test_a_renamed_company_is_found_by_its_new_name_for_all_its_jobs(): void
    {
        $this->live('Cook');
        $this->live('Waiter');

        $this->assertSame([], $this->titles('filter[q]=sinar'));

        $this->app['auth']->forgetGuards();
        $this->withToken($this->adminToken)->patchJson('/api/v1/company/profile', ['name' => 'Sinar Digital'])->assertNoContent();

        $this->assertEqualsCanonicalizing(['Cook', 'Waiter'], $this->titles('filter[q]=sinar'));
    }

    public function test_saving_a_job_through_the_api_fills_its_vector(): void
    {
        $this->app['auth']->forgetGuards();
        $id = (int) $this->withToken($this->adminToken)->postJson('/api/v1/company/jobs', ['title' => 'Quantum Plumber', 'description' => 'Fix the **flux** pipes.'])->assertCreated()->json('data.id');

        $this->assertNotNull(DB::table('job_jobs')->where('id', $id)->value('search_vector'));
        $vector = (string) DB::table('job_jobs')->where('id', $id)->value('search_vector');
        $this->assertStringContainsString("'quantum':1A", $vector);
        $this->assertStringContainsString("'flux'", $vector);
        $this->assertStringNotContainsString('*', $vector);
    }

    // ----- posting date -----

    public function test_the_posting_window_counts_from_now_on_the_publication_time(): void
    {
        $this->live('Today', ['published_at' => '2026-10-05 02:00:00']);
        $this->live('Two days', ['published_at' => '2026-10-03 08:00:00']);
        $this->live('Six days', ['published_at' => '2026-09-29 12:00:00']);
        $this->live('Old', ['published_at' => '2026-08-01 08:00:00']);

        $this->assertSame(['Today'], $this->titles('filter[posted_within]=1'));
        $this->assertSame(['Today', 'Two days'], $this->titles('filter[posted_within]=3'));
        $this->assertSame(['Today', 'Two days', 'Six days'], $this->titles('filter[posted_within]=7'));
        $this->assertSame(['Today', 'Two days', 'Six days'], $this->titles('filter[posted_within]=30'));
        $this->assertCount(4, $this->fetch('jobs')->json('data'));
    }

    public function test_only_the_offered_windows_are_accepted(): void
    {
        foreach (['0', '2', '31', 'abc', '-1'] as $value) {
            $this->fetch("jobs?filter[posted_within]={$value}")->assertStatus(422)->assertJsonValidationErrors(['filter.posted_within']);
        }
    }

    public function test_an_alert_may_carry_the_window_and_an_alert_about_one_new_job_ignores_it(): void
    {
        $this->assertSame(['posted_within' => 7], AlertCriteria::of(['posted_within' => 7])->toArray());

        foreach ([5, '7', 0] as $bad) {
            try {
                AlertCriteria::of(['q' => 'php', 'posted_within' => $bad]);
                $this->fail('A window of '.json_encode($bad).' should be refused.');
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame('invalid-criteria', $violation->errorCode());
            }
        }

        $old = $this->live('Old', ['published_at' => '2026-09-01 08:00:00']);
        $new = $this->live('New', ['published_at' => '2026-10-04 08:00:00']);
        $matcher = $this->app->make(JobMatcher::class);

        $this->assertSame([$new], $matcher->match(['posted_within' => 3], null, null, null, 10)->jobIds);
        $this->assertSame([$old], $matcher->match(['posted_within' => 3], null, null, $old, 10)->jobIds, 'the immediate alert of one job ignores the window');
    }

    // ----- similar jobs -----

    /** @return array{role: int, other_role: int, category: int, other_category: int, city: int, other_city: int, province: int} */
    private function places(): array
    {
        $chain = $this->locationChain();
        $otherCity = (int) DB::table('reference_cities')->insertGetId(['province_id' => $chain['province_id'], 'bps_code' => '3204', 'name' => 'BANDUNG BARAT', 'type' => 'city']);

        return [
            'role' => $this->referenceId('reference_job_roles', 'backend-developer'),
            'other_role' => $this->referenceId('reference_job_roles', 'chef'),
            'category' => $this->referenceId('reference_job_categories', 'engineering'),
            'other_category' => $this->referenceId('reference_job_categories', 'hospitality'),
            'city' => $chain['city_id'],
            'other_city' => $otherCity,
            'province' => $chain['province_id'],
        ];
    }

    public function test_similar_jobs_share_the_role_or_category_and_the_closest_come_first(): void
    {
        $p = $this->places();
        $second = $this->companyRowId('pt-kedua');
        $third = $this->companyRowId('pt-ketiga');

        $subject = $this->live('Backend Developer', ['role_id' => $p['role'], 'category_id' => $p['category'], 'city_id' => $p['city'], 'province_id' => $p['province']]);
        $sameCity = $this->live('Backend A', ['role_id' => $p['role'], 'category_id' => $p['category'], 'city_id' => $p['city'], 'province_id' => $p['province']], companyId: $second);
        $sameProvince = $this->live('Backend B', ['role_id' => $p['role'], 'category_id' => $p['category'], 'city_id' => $p['other_city'], 'province_id' => $p['province']], companyId: $second);
        $tooMany = $this->live('Backend C', ['role_id' => $p['role'], 'category_id' => $p['category']], companyId: $second);
        $category = $this->live('Software Tester', ['role_id' => $p['other_role'], 'category_id' => $p['category'], 'city_id' => $p['city'], 'province_id' => $p['province']], companyId: $third);
        $this->live('Cook', ['role_id' => $p['other_role'], 'category_id' => $p['other_category']], companyId: $third);
        $this->live('Backend Closed', ['role_id' => $p['role'], 'status' => 'closed']);
        $this->live('Backend Taken Down', ['role_id' => $p['role'], 'status' => 'taken_down']);
        $this->live('Backend Expired Deadline', ['role_id' => $p['role'], 'deadline' => '2026-10-01']);

        $response = $this->fetch("jobs/{$subject}/similar")->assertOk();
        $ids = array_map('intval', array_column($response->json('data'), 'id'));

        $this->assertSame([$sameCity, $sameProvince, $category], $ids, 'the same role first, then the same category, never the job itself, at most 2 of one company');
        $this->assertNotContains($tooMany, $ids);
        $this->assertStringContainsString('max-age=600', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_level_within_one_step_comes_before_a_level_further_away(): void
    {
        $p = $this->places();
        $levels = [];

        foreach ([1 => 'junior', 2 => 'middle', 3 => 'senior', 4 => 'lead'] as $order => $slug) {
            $levels[$slug] = $this->referenceId('reference_job_levels', $slug, ['sort_order' => $order]);
        }

        $subject = $this->live('Backend Developer', ['role_id' => $p['role'], 'job_level_id' => $levels['middle']]);
        $far = $this->live('Far level', ['role_id' => $p['role'], 'job_level_id' => $levels['lead'], 'published_at' => '2026-10-04 08:00:00'], companyId: $this->companyRowId('pt-a'));
        $near = $this->live('Near level', ['role_id' => $p['role'], 'job_level_id' => $levels['senior'], 'published_at' => '2026-10-02 08:00:00'], companyId: $this->companyRowId('pt-b'));
        $same = $this->live('Same level', ['role_id' => $p['role'], 'job_level_id' => $levels['middle'], 'published_at' => '2026-10-01 08:00:00'], companyId: $this->companyRowId('pt-c'));

        $ids = array_map('intval', array_column($this->fetch("jobs/{$subject}/similar")->assertOk()->json('data'), 'id'));

        $this->assertEqualsCanonicalizing([$near, $same], array_slice($ids, 0, 2));
        $this->assertSame($far, $ids[2]);
        $this->assertSame([$near, $same], array_slice($ids, 0, 2), 'within the step, the newest comes first');
    }

    public function test_at_most_six_similar_jobs_are_returned(): void
    {
        $p = $this->places();
        $subject = $this->live('Backend Developer', ['role_id' => $p['role']]);

        foreach (range(1, 8) as $number) {
            $this->live("Backend {$number}", ['role_id' => $p['role']], companyId: $this->companyRowId("pt-extra-{$number}"));
        }

        $this->assertCount(6, $this->fetch("jobs/{$subject}/similar")->assertOk()->json('data'));
    }

    public function test_the_job_may_be_closed_or_expired_but_not_taken_down_held_or_unpublished(): void
    {
        $p = $this->places();
        $this->live('Backend Match', ['role_id' => $p['role']], companyId: $this->otherCompanyId);

        foreach (['active', 'closed', 'expired'] as $status) {
            $id = $this->live("Subject {$status}", ['role_id' => $p['role'], 'status' => $status]);
            $this->assertNotEmpty($this->fetch("jobs/{$id}/similar")->assertOk()->json('data'), $status);
        }

        foreach (['taken_down', 'draft', 'pending_review', 'rejected'] as $status) {
            $id = $this->live("Subject {$status}", ['role_id' => $p['role'], 'status' => $status]);
            $this->fetch("jobs/{$id}/similar")->assertNotFound();
        }

        $held = $this->live('Subject held', ['role_id' => $p['role'], 'report_hold_at' => now()]);
        $this->fetch("jobs/{$held}/similar")->assertNotFound();
        $this->fetch('jobs/999999/similar')->assertNotFound();
    }

    public function test_a_job_without_role_and_category_has_no_similar_jobs(): void
    {
        $subject = $this->live('Anything');
        $this->live('Something else');

        $this->fetch("jobs/{$subject}/similar")->assertOk()->assertExactJson(['data' => []]);
    }
}
