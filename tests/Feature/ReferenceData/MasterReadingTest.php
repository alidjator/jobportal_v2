<?php

namespace Tests\Feature\ReferenceData;

use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

final class MasterReadingTest extends FeatureTestCase
{
    /** @param  array<string, mixed>  $extra */
    private function insert(string $table, string $id, string $en, string $slug, int $sort = 0, bool $active = true, array $extra = []): void
    {
        DB::table($table)->insert([
            'name' => json_encode(['id' => $id, 'en' => $en]), 'slug' => $slug, 'sort_order' => $sort, 'is_active' => $active,
            'created_at' => now(), 'updated_at' => now(), ...$extra,
        ]);
    }

    public function test_a_master_lists_active_items_in_curated_order_in_the_request_language(): void
    {
        $this->insert('reference_job_levels', 'Manajer', 'Manager', 'manager', 2);
        $this->insert('reference_job_levels', 'Junior', 'Junior', 'junior', 1);
        $this->insert('reference_job_levels', 'Magang', 'Intern', 'intern', 1);
        $this->insert('reference_job_levels', 'Tersembunyi', 'Hidden', 'hidden', 0, false);

        $english = $this->getJson('/api/v1/job-levels', ['Accept-Language' => 'en'])->assertOk();
        $this->assertSame(['Intern', 'Junior', 'Manager'], array_column($english->json('data'), 'name'));
        $this->assertSame(['intern', 'junior', 'manager'], array_column($english->json('data'), 'slug'));
        $this->assertArrayNotHasKey('links', $english->json());
        $this->assertArrayNotHasKey('meta', $english->json());

        $this->getJson('/api/v1/job-levels', ['Accept-Language' => 'id'])->assertOk()->assertJsonPath('data.0.name', 'Junior');
    }

    public function test_extra_columns_appear_at_the_top_level(): void
    {
        $this->insert('reference_education_levels', 'Sarjana', 'Bachelor', 'bachelor', 1, extra: ['rank' => 4]);
        $this->insert('reference_experience_levels', 'Pemula', 'Entry', 'entry', 1, extra: ['min_years' => 0, 'max_years' => 1]);
        $this->insert('reference_languages', 'Indonesia', 'Indonesian', 'indonesian', 1, extra: ['code' => 'id']);

        $this->getJson('/api/v1/education-levels')->assertOk()->assertJsonPath('data.0.rank', 4);
        $this->getJson('/api/v1/experience-levels')->assertOk()->assertJsonPath('data.0.min_years', 0)->assertJsonPath('data.0.max_years', 1);
        $this->getJson('/api/v1/languages')->assertOk()->assertJsonPath('data.0.code', 'id');
    }

    public function test_only_approved_benefits_are_listed(): void
    {
        $this->insert('reference_benefits', 'Asuransi', 'Insurance', 'insurance', 1, extra: ['status' => 'active']);
        $this->insert('reference_benefits', 'Usulan', 'Proposal', 'proposal', 2, extra: ['status' => 'pending']);
        $this->insert('reference_benefits', 'Ditolak', 'Rejected', 'rejected', 3, extra: ['status' => 'rejected']);

        $this->getJson('/api/v1/benefits')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'insurance');
    }

    public function test_the_list_can_be_searched_in_both_languages_and_sorted_by_name(): void
    {
        $this->insert('reference_industries', 'Teknologi', 'Technology', 'technology', 1);
        $this->insert('reference_industries', 'Keuangan', 'Finance', 'finance', 2);
        $this->insert('reference_industries', 'Kesehatan', 'Healthcare', 'healthcare', 3);

        $this->getJson('/api/v1/industries?filter[q]=tekno')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'technology');
        $this->getJson('/api/v1/industries?filter[q]=FINANCE')->assertOk()->assertJsonPath('data.0.slug', 'finance');

        $byName = $this->getJson('/api/v1/industries?sort=name', ['Accept-Language' => 'en'])->assertOk();
        $this->assertSame(['Finance', 'Healthcare', 'Technology'], array_column($byName->json('data'), 'name'));
        $reversed = $this->getJson('/api/v1/industries?sort=-name', ['Accept-Language' => 'en'])->assertOk();
        $this->assertSame(['Technology', 'Healthcare', 'Finance'], array_column($reversed->json('data'), 'name'));
    }

    public function test_search_characters_are_taken_literally(): void
    {
        $this->insert('reference_industries', '100% Digital', '100% Digital', 'digital', 1);
        $this->insert('reference_industries', 'Teknologi', 'Technology', 'technology', 2);

        $this->getJson('/api/v1/industries?filter[q]=%')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'digital');
        $this->getJson('/api/v1/industries?filter[q]=_')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unknown_filters_and_sorts_are_refused_with_422(): void
    {
        $this->getJson('/api/v1/industries?filter[color]=red')->assertStatus(422)->assertJsonValidationErrors(['filter'], 'errors');
        $this->getJson('/api/v1/industries?sort=-id')->assertStatus(422)->assertJsonValidationErrors(['sort'], 'errors');
        $this->getJson('/api/v1/industries?filter[kind]=hard')->assertStatus(422);
    }

    public function test_a_bounded_master_ignores_page_parameters_and_returns_everything(): void
    {
        foreach (range(1, 25) as $number) {
            $this->insert('reference_religions', "Agama {$number}", "Religion {$number}", "religion-{$number}", $number);
        }

        $this->getJson('/api/v1/religions?per_page=5&page=2')->assertOk()->assertJsonCount(25, 'data');
    }

    public function test_a_paginated_master_returns_links_and_meta_with_the_total(): void
    {
        foreach (range(1, 45) as $number) {
            $this->insert('reference_skills', "Skill {$number}", "Skill {$number}", "skill-{$number}", $number, extra: ['kind' => $number % 2 ? 'hard' : 'soft']);
        }

        $first = $this->getJson('/api/v1/skills')->assertOk();
        $this->assertCount(20, $first->json('data'));
        $this->assertSame(45, $first->json('meta.total'));
        $this->assertSame(20, $first->json('meta.per_page'));
        $this->assertSame(3, $first->json('meta.last_page'));
        $this->assertNotNull($first->json('links.next'));

        $last = $this->getJson('/api/v1/skills?page=3')->assertOk();
        $this->assertCount(5, $last->json('data'));
        $this->assertNull($last->json('links.next'));

        $small = $this->getJson('/api/v1/skills?per_page=2&page=2')->assertOk();
        $this->assertSame(['skill-3', 'skill-4'], array_column($small->json('data'), 'slug'));
        $this->assertStringContainsString('per_page=2', $small->json('links.next'));
    }

    public function test_page_parameters_out_of_range_are_refused_not_capped(): void
    {
        $this->getJson('/api/v1/skills?per_page=101')->assertStatus(422)->assertJsonValidationErrors(['per_page'], 'errors');
        $this->getJson('/api/v1/skills?per_page=0')->assertStatus(422);
        $this->getJson('/api/v1/skills?page=0')->assertStatus(422)->assertJsonValidationErrors(['page'], 'errors');
        $this->getJson('/api/v1/skills?per_page=100')->assertOk();
        $this->getJson('/api/v1/skills?per_page=1&page=1')->assertOk();
    }

    public function test_skills_can_be_filtered_by_kind_and_searched(): void
    {
        $this->insert('reference_skills', 'Pemrograman', 'Programming', 'programming', 1, extra: ['kind' => 'hard']);
        $this->insert('reference_skills', 'Komunikasi', 'Communication', 'communication', 2, extra: ['kind' => 'soft']);
        $this->insert('reference_skills', 'Kerja sama tim', 'Teamwork', 'teamwork', 3, extra: ['kind' => 'soft']);

        $soft = $this->getJson('/api/v1/skills?filter[kind]=soft')->assertOk();
        $this->assertSame(['communication', 'teamwork'], array_column($soft->json('data'), 'slug'));
        $this->assertSame(2, $soft->json('meta.total'));

        $this->getJson('/api/v1/skills?filter[kind]=soft&filter[q]=team')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'soft');
        $this->getJson('/api/v1/skills?filter[kind]=other')->assertStatus(422);
    }

    public function test_job_roles_and_tags_are_paginated_and_searchable(): void
    {
        $this->insert('reference_job_roles', 'Pengembang', 'Developer', 'developer', 1);
        $this->insert('reference_tags', 'Jarak jauh', 'Remote', 'remote', 1);

        $this->getJson('/api/v1/job-roles?filter[q]=dev')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/tags?filter[q]=jarak')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.slug', 'remote');
        $this->getJson('/api/v1/tags?filter[kind]=hard')->assertStatus(422);
    }

    public function test_public_responses_can_be_cached_for_an_hour_and_revalidated(): void
    {
        $this->insert('reference_job_types', 'Penuh waktu', 'Full time', 'full-time', 1);

        $response = $this->getJson('/api/v1/job-types')->assertOk();
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('Accept-Language', $response->headers->get('Vary'));
        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->getJson('/api/v1/job-types', ['If-None-Match' => $etag])->assertStatus(304);

        $this->insert('reference_job_types', 'Magang', 'Internship', 'internship', 2);
        $this->getJson('/api/v1/job-types', ['If-None-Match' => $etag])->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_the_etag_differs_per_language(): void
    {
        $this->insert('reference_job_types', 'Penuh waktu', 'Full time', 'full-time', 1);

        $id = $this->getJson('/api/v1/job-types', ['Accept-Language' => 'id'])->headers->get('ETag');
        $en = $this->getJson('/api/v1/job-types', ['Accept-Language' => 'en'])->headers->get('ETag');

        $this->assertNotSame($id, $en);
    }

    public function test_every_master_of_the_catalog_has_a_public_endpoint(): void
    {
        foreach (['job-categories', 'job-roles', 'job-types', 'job-levels', 'work-times', 'experience-levels', 'education-levels', 'salary-periods', 'skills', 'tags',
            'benefits', 'industries', 'organization-types', 'team-sizes', 'religions', 'nationalities', 'languages'] as $key) {
            $this->getJson("/api/v1/{$key}")->assertOk()->assertJsonStructure(['data']);
        }
    }

    public function test_the_masters_need_no_login_even_with_a_stale_token(): void
    {
        $this->withToken('999|stale')->getJson('/api/v1/job-levels')->assertOk();
    }
}
