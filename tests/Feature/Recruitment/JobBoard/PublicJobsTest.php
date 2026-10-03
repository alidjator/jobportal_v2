<?php

namespace Tests\Feature\Recruitment\JobBoard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicJobsTest extends JobBoardTestCase
{
    public function test_home_returns_all_sections(): void
    {
        $company = $this->makeCompany();
        $job = $this->makeJob($company['company'], ['featured' => 1]);
        DB::table('testimonials')->insert([
            'name' => 'Tester', 'position' => 'CEO', 'description' => 'Great', 'stars' => 5, 'code' => 'en',
        ]);

        $response = $this->getJson('/api/home')->assertOk()->assertJsonStructure(['data' => [
            'top_companies', 'featured_jobs', 'states' => ['live_jobs', 'new_jobs', 'companies', 'candidates'],
            'most_popular_vacancies', 'popular_categories', 'testimonial', 'top_categories',
        ]]);

        $featured = collect($response->json('data.featured_jobs'))->firstWhere('slug', $job['slug']);
        $this->assertNotNull($featured);
        $this->assertSame($job['type']['name'], $featured['job_type']);
        $this->assertSame('1K - 5K USD', $featured['salary']);
        $this->assertTrue($featured['is_featured']);
        $this->assertFalse($featured['bookmarked']);
        $this->assertStringEndsWith('/job/'.$job['slug'], $featured['job_details']);

        $this->assertGreaterThanOrEqual(1, $response->json('data.states.live_jobs'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.states.companies'));
        $this->assertContains('CEO', array_column($response->json('data.testimonial'), 'designation'));

        $roles = collect($response->json('data.most_popular_vacancies'));
        $this->assertSame(['id', 'name', 'open_position_count'], array_keys($roles->first()));
        $this->assertTrue(array_is_list($response->json('data.most_popular_vacancies')));
        $this->assertSame(['id', 'name', 'image', 'open_jobs_count'], array_keys($response->json('data.popular_categories.0')));
        $this->assertSame(['name', 'username', 'company_logo', 'country', 'open_jobs_count'], array_keys($response->json('data.top_companies.0')));
    }

    public function test_home_marks_bookmarked_featured_jobs_for_logged_in_candidate(): void
    {
        $company = $this->makeCompany();
        $job = $this->makeJob($company['company'], ['featured' => 1]);
        $candidate = $this->makeCandidate();
        DB::table('bookmark_candidate_job')->insert(['candidate_id' => $candidate['candidate'], 'job_id' => $job['id']]);

        $this->actingAsUser($candidate['user']);

        $featured = collect($this->getJson('/api/home')->assertOk()->json('data.featured_jobs'))->firstWhere('slug', $job['slug']);

        $this->assertTrue($featured['bookmarked']);
    }

    public function test_expired_featured_promotion_is_not_reported_as_featured(): void
    {
        $company = $this->makeCompany();
        $job = $this->makeJob($company['company'], ['featured' => 1, 'featured_until' => now()->subDays(3)->toDateString(), 'title' => $m = $this->marker()]);
        // diubah di dalam transaksi test (di-rollback)
        DB::table('settings')->exists()
            ? DB::table('settings')->update(['featured_job_days' => 7])
            : DB::table('settings')->insert(['featured_job_days' => 7, 'created_at' => now()]);

        $item = collect($this->getJson('/api/jobs?keyword='.$m)->assertOk()->json('data.data'))->firstWhere('slug', $job['slug']);

        $this->assertFalse($item['is_featured']);
    }

    public function test_jobs_list_has_paginator_shape_and_item_fields(): void
    {
        $company = $this->makeCompany();
        $m = $this->marker();
        $job = $this->makeJob($company['company'], ['title' => "$m one", 'is_remote' => 1]);
        $this->makeJob($company['company'], ['title' => "$m two"]);

        $response = $this->getJson("/api/jobs?keyword=$m&paginate=1")->assertOk();

        $response->assertJsonStructure(['data' => [
            'current_page', 'data', 'first_page_url', 'from', 'last_page', 'last_page_url', 'links',
            'next_page_url', 'path', 'per_page', 'prev_page_url', 'to', 'total',
        ]]);
        $this->assertSame(2, $response->json('data.total'));
        $this->assertSame(1, $response->json('data.per_page'));
        $this->assertCount(1, $response->json('data.data'));
        $this->assertStringContainsString("keyword=$m", $response->json('data.next_page_url'));

        $items = collect($this->getJson("/api/jobs?keyword=$m")->json('data.data'));
        $item = $items->firstWhere('slug', $job['slug']);

        $this->assertSame([
            'id', 'title', 'slug', 'job_details', 'company_name', 'company_logo', 'job_type', 'job_role', 'category',
            'country', 'is_featured', 'is_highlighted', 'deadline', 'salary', 'salary_mode', 'min_salary', 'max_salary',
            'bookmarked', 'allAppliedJobs_count',
        ], array_keys($item));
        $this->assertSame($job['id'], $item['id']);
        $this->assertSame($job['type']['name'], $item['job_type']);
        $this->assertSame($job['role']['name'], $item['job_role']);
        $this->assertSame($job['category']['name'], $item['category']);
        $this->assertSame(1000, $item['min_salary']);
        $this->assertSame('1K - 5K USD', $item['salary']);
        $this->assertFalse($item['bookmarked']);
        $this->assertSame(0, $item['allAppliedJobs_count']);
    }

    public function test_jobs_only_lists_active_jobs_without_pending_edits(): void
    {
        $company = $this->makeCompany();
        $m = $this->marker();
        $this->makeJob($company['company'], ['title' => "$m active"]);
        $this->makeJob($company['company'], ['title' => "$m pending", 'status' => 'pending']);
        $this->makeJob($company['company'], ['title' => "$m expired", 'status' => 'expired']);
        $this->makeJob($company['company'], ['title' => "$m edited", 'waiting_for_edit_approval' => 1]);

        $titles = array_column($this->getJson("/api/jobs?keyword=$m")->json('data.data'), 'title');

        $this->assertSame(["$m active"], $titles);
    }

    public function test_jobs_filters(): void
    {
        $company = $this->makeCompany();
        $m = $this->marker();

        $type = $this->makeLookup('job_types', 'job_type_id', 'job_type_translations');
        $experience = $this->makeLookup('experiences', 'experience_id', 'experience_translations');
        $education = $this->makeLookup('education', 'education_id', 'education_translations');
        $tag = $this->makeLookup('tags', 'tag_id', 'tag_translations');

        $target = $this->makeJob($company['company'], [
            'title' => "$m target", '_type' => $type, 'experience_id' => $experience['id'], 'education_id' => $education['id'],
            'is_remote' => 1, 'min_salary' => 7000, 'max_salary' => 9000, 'featured' => 1, 'country' => 'Wakanda',
        ]);
        DB::table('job_tag')->insert(['job_id' => $target['id'], 'tag_id' => $tag['id']]);
        $other = $this->makeJob($company['company'], ['title' => "$m other", 'min_salary' => 100, 'max_salary' => 200, 'country' => 'Narnia']);

        $slugs = fn (string $query): array => array_column($this->getJson("/api/jobs?keyword=$m&".$query)->assertOk()->json('data.data'), 'slug');

        $this->assertEqualsCanonicalizing([$target['slug'], $other['slug']], $slugs(''));
        $this->assertSame([$target['slug']], $slugs('job_type='.urlencode($type['name'])));
        $this->assertSame([$target['slug']], $slugs('experience='.urlencode($experience['name'])));
        $this->assertSame([$target['slug']], $slugs('education='.urlencode($education['name'])));
        $this->assertSame([$target['slug']], $slugs('tag='.urlencode($tag['name'])));
        $this->assertSame([$target['slug']], $slugs('is_remote=1'));
        $this->assertEqualsCanonicalizing([$target['slug'], $other['slug']], $slugs('is_remote=0'));
        $this->assertSame([$target['slug']], $slugs('price_min=5000'));
        $this->assertSame([$other['slug']], $slugs('price_max=300'));
        $this->assertSame([$target['slug']], $slugs('sort_by=featured'));
        $this->assertSame([$target['slug']], $slugs('location=Wakan'));
        $this->assertSame([$target['slug']], $slugs('category='.$target['category']['id']));
        $this->assertSame([$target['slug']], $slugs('job_role='.$target['role']['id']));
        $this->assertEqualsCanonicalizing([$target['slug'], $other['slug']], $slugs('company='.urlencode(DB::table('users')->where('id', $company['user'])->value('username'))));
        $this->assertSame([], $slugs('company=nobody-'.Str::random(6)));
        // nama tipe/pengalaman/pendidikan tak dikenal => tidak ada hasil
        $this->assertSame([], $slugs('job_type=NoSuchType'.Str::random(6)));
        $this->assertSame([], $slugs('experience=NoSuch'.Str::random(6)));
        $this->assertSame([], $slugs('education=NoSuch'.Str::random(6)));
        // sort_by=latest: id terbaru lebih dulu
        $this->assertSame([$other['slug'], $target['slug']], $slugs('sort_by=latest'));
    }

    public function test_jobs_selected_country_header_filters_by_country_name(): void
    {
        $company = $this->makeCompany();
        $m = $this->marker();
        $countryName = 'Country'.Str::random(8);
        $countryId = DB::table('countries')->insertGetId(['name' => $countryName, 'slug' => Str::lower($countryName)]);
        $inside = $this->makeJob($company['company'], ['title' => "$m in", 'country' => $countryName]);
        $this->makeJob($company['company'], ['title' => "$m out", 'country' => 'Elsewhere']);

        $slugs = array_column(
            $this->getJson("/api/jobs?keyword=$m", ['selected_country' => (string) $countryId])->assertOk()->json('data.data'),
            'slug',
        );

        $this->assertSame([$inside['slug']], $slugs);
    }

    public function test_jobs_lat_long_filter_keeps_status_constraints(): void
    {
        $company = $this->makeCompany();
        $m = $this->marker();
        $near = $this->makeJob($company['company'], ['title' => "$m near", 'lat' => -6.2, 'long' => 106.8]);
        $this->makeJob($company['company'], ['title' => "$m far", 'lat' => 40.7, 'long' => -74.0]);
        $this->makeJob($company['company'], ['title' => "$m pending", 'status' => 'pending', 'lat' => -6.2, 'long' => 106.8]);

        $slugs = array_column($this->getJson("/api/jobs?keyword=$m&lat=-6.2&long=106.8&radius=25")->assertOk()->json('data.data'), 'slug');

        $this->assertSame([$near['slug']], $slugs);

        // masukan lat/long berbahaya tidak menjadi SQL
        $this->getJson('/api/jobs?lat=1)%20or%201=1--&long=2')->assertOk();
    }

    public function test_jobs_salary_uses_currency_header(): void
    {
        $base = config('jobportal.default_currency');
        DB::table('currencies')->insert(['name' => 'Base', 'code' => $base, 'rate' => 1, 'symbol' => '$', 'symbol_position' => 'left']);
        DB::table('currencies')->insert(['name' => 'Half', 'code' => 'HLF', 'rate' => 0.5, 'symbol' => 'H', 'symbol_position' => 'left']);

        $company = $this->makeCompany();
        $m = $this->marker();
        $this->makeJob($company['company'], ['title' => "$m range"]);
        $this->makeJob($company['company'], ['title' => "$m custom", 'salary_mode' => 'custom', 'custom_salary' => 'Negotiable']);

        $salaries = fn (array $headers): array => collect($this->getJson("/api/jobs?keyword=$m", $headers)->json('data.data'))
            ->pluck('salary', 'title')->all();

        $this->assertEquals(["$m range" => "1K - 5K $base", "$m custom" => 'Negotiable'], $salaries([]));
        $this->assertEquals(["$m range" => '2K - 10K HLF', "$m custom" => 'Negotiable'], $salaries(['X-Currency' => 'hlf']));
        // mata uang tak dikenal => kembali ke mata uang dasar
        $this->assertSame("1K - 5K $base", $salaries(['X-Currency' => 'ZZZ'])["$m range"]);
    }

    public function test_jobs_flags_bookmark_for_authenticated_candidate(): void
    {
        $company = $this->makeCompany();
        $m = $this->marker();
        $job = $this->makeJob($company['company'], ['title' => "$m x"]);
        $candidate = $this->makeCandidate();
        DB::table('bookmark_candidate_job')->insert(['candidate_id' => $candidate['candidate'], 'job_id' => $job['id']]);
        DB::table('applied_jobs')->insert(['candidate_id' => $candidate['candidate'], 'job_id' => $job['id']]);

        $this->actingAsUser($candidate['user']);

        $item = $this->getJson("/api/jobs?keyword=$m")->assertOk()->json('data.data.0');

        $this->assertTrue($item['bookmarked']);
        $this->assertSame(1, $item['allAppliedJobs_count']);
    }

    public function test_job_detail_shape(): void
    {
        $company = $this->makeCompany('Detail Co');
        $job = $this->makeJob($company['company'], ['featured' => 1, 'featured_until' => '2099-01-01', 'region' => 3201]);
        $benefit = $this->makeLookup('benefits', 'benefit_id', 'benefit_translations');
        $tag = $this->makeLookup('tags', 'tag_id', 'tag_translations');
        DB::table('job_benefit')->insert(['job_id' => $job['id'], 'benefit_id' => $benefit['id']]);
        DB::table('job_tag')->insert(['job_id' => $job['id'], 'tag_id' => $tag['id']]);
        $related = $this->makeJob($company['company'], ['_category' => $job['category']]);
        $this->makeJob($company['company'], ['_category' => $job['category'], 'status' => 'pending']);

        $response = $this->getJson('/api/jobs/'.$job['slug'])->assertOk();

        $this->assertSame(['job', 'related_jobs', 'resumes'], array_keys($response->json('data')));
        $detail = $response->json('data.job');
        $this->assertSame([
            'id', 'title', 'slug', 'vacancies', 'salary', 'min_salary', 'max_salary', 'salary_mode', 'custom_salary', 'job_type',
            'deadline', 'description', 'status', 'apply_on', 'apply_email', 'apply_url', 'featured', 'featured_until', 'highlight',
            'highlight_until', 'is_remote', 'bookmarked', 'applied', 'days_remaining', 'deadline_active', 'posted_at', 'can_apply',
            'location', 'benefits', 'tags', 'role', 'experience', 'education', 'company',
        ], array_keys($detail));
        $this->assertSame($job['id'], $detail['id']);
        $this->assertSame($job['type']['name'], $detail['job_type']);
        $this->assertSame([$benefit['name']], $detail['benefits']);
        $this->assertSame([$tag['name']], $detail['tags']);
        $this->assertSame($job['role']['name'], $detail['role']);
        $this->assertNull($detail['experience']);
        $this->assertTrue($detail['featured']);
        $this->assertStringStartsWith('2099-01-01T', $detail['featured_until']);
        $this->assertTrue($detail['deadline_active']);
        $this->assertTrue($detail['can_apply']);
        $this->assertFalse($detail['bookmarked']);
        $this->assertFalse($detail['applied']);
        $this->assertSame('3201 , Indonesia', $detail['location']);
        $this->assertSame('1K - 5K '.config('jobportal.default_currency'), $detail['salary']);
        $this->assertSame(['name', 'slug', 'logo', 'website', 'address', 'contact_info', 'social_links'], array_keys($detail['company']));
        $this->assertSame('https://acme.test', $detail['company']['website']);
        $this->assertSame([], $response->json('data.resumes'));

        $relatedSlugs = array_column($response->json('data.related_jobs'), 'slug');
        $this->assertSame([$related['slug']], $relatedSlugs);
        $this->assertSame($related['role']['id'], $response->json('data.related_jobs.0.job_role'));
        $this->assertSame($related['category']['id'], $response->json('data.related_jobs.0.category'));
    }

    public function test_job_detail_unknown_slug_is_404(): void
    {
        $this->getJson('/api/jobs/nope-'.Str::random(8))->assertNotFound()->assertJson(['error' => 'Job not found']);
    }

    public function test_pending_job_is_hidden_from_everyone_but_owner_company(): void
    {
        $company = $this->makeCompany();
        $job = $this->makeJob($company['company'], ['status' => 'pending']);

        $this->getJson('/api/jobs/'.$job['slug'])->assertNotFound()->assertJson(['error' => 'Job not found']);

        $candidate = $this->makeCandidate();
        $this->actingAsUser($candidate['user']);
        $this->getJson('/api/jobs/'.$job['slug'])->assertNotFound();

        $other = $this->makeCompany();
        $this->actingAsUser($other['user']);
        $this->getJson('/api/jobs/'.$job['slug'])->assertNotFound();

        $this->actingAsUser($company['user']);
        $this->getJson('/api/jobs/'.$job['slug'])->assertOk()->assertJsonPath('data.job.status', 'pending');
    }

    public function test_job_detail_includes_resumes_and_flags_for_candidate(): void
    {
        $company = $this->makeCompany();
        $job = $this->makeJob($company['company']);
        $candidate = $this->makeCandidate();
        $resume = $this->makeResume($candidate['candidate']);
        DB::table('bookmark_candidate_job')->insert(['candidate_id' => $candidate['candidate'], 'job_id' => $job['id']]);
        DB::table('applied_jobs')->insert(['candidate_id' => $candidate['candidate'], 'job_id' => $job['id']]);

        $this->actingAsUser($candidate['user']);

        $response = $this->getJson('/api/jobs/'.$job['slug'])->assertOk();

        $this->assertTrue($response->json('data.job.bookmarked'));
        $this->assertTrue($response->json('data.job.applied'));
        $this->assertSame($resume, $response->json('data.resumes.0.id'));
        $this->assertSame(['id', 'candidate_id', 'name', 'file', 'created_at', 'updated_at', 'file_size', 'file_url'], array_keys($response->json('data.resumes.0')));
    }
}
